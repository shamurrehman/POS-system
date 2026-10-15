<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SaleService
{
    public function __construct(private StockService $stock) {}

    /**
     * Create a sale. Prices always come from the database, never from the browser.
     *
     * @param  array{items: list<array{product_id:int, quantity:int, batch_id?:?int}>, discount?:float, payment_method:string, paid_amount?:float, customer_name?:?string, customer_phone?:?string, doctor_name?:?string, notes?:?string}  $data
     */
    public function create(array $data, User $user): Sale
    {
        $lines = collect($data['items'])
            ->groupBy(fn ($i) => $i['product_id'].'-'.($i['batch_id'] ?? ''))
            ->map(fn ($group) => [
                'product_id' => (int) $group[0]['product_id'],
                'batch_id' => $group[0]['batch_id'] ?? null,
                'quantity' => (int) $group->sum('quantity'),
            ])->values();

        try {
            return DB::transaction(function () use ($lines, $data, $user) {
                $products = Product::whereIn('id', $lines->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');

                $subtotal = 0.0;
                foreach ($lines as $line) {
                    $product = $products->get($line['product_id']);
                    if (! $product || ! $product->is_active) {
                        throw new RuntimeException('One of the items is no longer available for sale.');
                    }
                    if ((float) $product->sale_price <= 0) {
                        throw new RuntimeException("No selling price is set for {$product->display_name}. Set it under Medicines → Edit or on Stock In.");
                    }
                    $subtotal += round((float) $product->sale_price * $line['quantity'], 2);
                }

                $discount = round(max(0, (float) ($data['discount'] ?? 0)), 2);
                if ($discount > $subtotal) {
                    throw new RuntimeException('Discount cannot be more than the bill amount.');
                }
                if (! $user->isManagerOrAdmin() && $subtotal > 0) {
                    $maxPct = Settings::float('cashier_max_discount');
                    if ($discount / $subtotal * 100 > $maxPct + 0.001) {
                        throw new RuntimeException("Cashiers can give at most {$maxPct}% discount. Ask a manager.");
                    }
                }

                $taxRate = Settings::float('tax_rate');
                $tax = round(($subtotal - $discount) * $taxRate / 100, 2);
                $total = round($subtotal - $discount + $tax, 2);

                $method = $data['payment_method'];
                $paid = $method === 'cash' ? round((float) ($data['paid_amount'] ?? $total), 2) : $total;
                if ($paid + 0.001 < $total) {
                    throw new RuntimeException('Amount received is less than the bill total.');
                }

                $sale = Sale::create([
                    'invoice_no' => $this->nextNumber(Sale::class, 'invoice_no', Settings::get('invoice_prefix', 'INV')),
                    'customer_name' => $data['customer_name'] ?? null,
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'doctor_name' => $data['doctor_name'] ?? null,
                    'user_id' => $user->id,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                    'paid_amount' => $paid,
                    'change_amount' => round($paid - $total, 2),
                    'payment_method' => $method,
                    'status' => 'completed',
                    'notes' => $data['notes'] ?? null,
                ]);

                $costTotal = 0.0;
                foreach ($lines as $line) {
                    $product = $products->get($line['product_id']);
                    $allocations = $this->stock->deductFefo(
                        $product, $line['quantity'], 'sale', $sale, $sale->invoice_no, null, $line['batch_id']
                    );

                    foreach ($allocations as $alloc) {
                        $batch = $alloc['batch'];
                        $qty = $alloc['quantity'];
                        $unitCost = (float) $batch->purchase_price;
                        $costTotal += $unitCost * $qty;

                        $sale->items()->create([
                            'product_id' => $product->id,
                            'batch_id' => $batch->id,
                            'product_name' => $product->display_name,
                            'batch_no' => $batch->batch_no,
                            'expiry_date' => $batch->expiry_date,
                            'quantity' => $qty,
                            'unit_price' => $product->sale_price,
                            'unit_cost' => $unitCost,
                            'line_total' => round((float) $product->sale_price * $qty, 2),
                        ]);
                    }
                }

                $sale->update(['cost_total' => round($costTotal, 2)]);
                ActivityLog::record('sale.created', "Sale {$sale->invoice_no} for ".money($total), $sale, $user->id);

                return $sale;
            });
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }
    }

    /**
     * Return items from a sale. Refunds are proportional to what the customer actually paid,
     * so a sale-level discount and tax are shared fairly across the returned lines.
     *
     * @param  array<int, int>  $quantities  sale_item_id => quantity to return
     */
    public function processReturn(Sale $sale, array $quantities, bool $restock, ?string $reason, User $user): SaleReturn
    {
        try {
            return DB::transaction(function () use ($sale, $quantities, $restock, $reason, $user) {
                $sale = Sale::lockForUpdate()->findOrFail($sale->id);
                $items = SaleItem::where('sale_id', $sale->id)->lockForUpdate()->get()->keyBy('id');

                $quantities = array_filter(array_map('intval', $quantities), fn ($q) => $q > 0);
                if (! $quantities) {
                    throw new RuntimeException('Select at least one item to return.');
                }

                $paidRatio = (float) $sale->subtotal > 0 ? (float) $sale->total / (float) $sale->subtotal : 0;
                $taxRatio = (float) $sale->total > 0 ? (float) $sale->tax / (float) $sale->total : 0;

                $return = SaleReturn::create([
                    'return_no' => $this->nextNumber(SaleReturn::class, 'return_no', 'RET'),
                    'sale_id' => $sale->id,
                    'user_id' => $user->id,
                    'reason' => $reason,
                ]);

                $refund = 0.0;
                $restockedCost = 0.0;
                foreach ($quantities as $itemId => $qty) {
                    $item = $items->get($itemId);
                    if (! $item) {
                        throw new RuntimeException('Invalid item in return.');
                    }
                    if ($qty > $item->returnable_quantity) {
                        throw new RuntimeException("Only {$item->returnable_quantity} of {$item->product_name} can be returned.");
                    }

                    $amount = round((float) $item->unit_price * $qty * $paidRatio, 2);
                    $refund += $amount;

                    $return->items()->create([
                        'sale_item_id' => $item->id,
                        'quantity' => $qty,
                        'amount' => $amount,
                        'restocked' => $restock && $item->batch,
                    ]);
                    $item->increment('returned_quantity', $qty);

                    if ($restock && $item->batch) {
                        $this->stock->addToBatch($item->batch, $qty, 'sale_return', $return, $return->return_no, "Return of {$sale->invoice_no}");
                        $restockedCost += (float) $item->unit_cost * $qty;
                    }
                }

                $refund = min($refund, (float) $sale->total - (float) $sale->returned_amount);
                $return->update([
                    'total' => $refund,
                    'tax' => round($refund * $taxRatio, 2),
                    'cost_total' => round($restockedCost, 2),
                ]);

                $fullyReturned = $items->every(fn (SaleItem $i) => $i->fresh()->returned_quantity >= $i->quantity);
                $sale->update([
                    'returned_amount' => round((float) $sale->returned_amount + $refund, 2),
                    'status' => $fullyReturned ? 'returned' : 'partially_returned',
                ]);

                ActivityLog::record('sale.returned', "Return {$return->return_no} on {$sale->invoice_no}: ".money($refund), $return, $user->id);

                return $return;
            });
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['return' => $e->getMessage()]);
        }
    }

    /** Sequential, human-friendly numbers such as INV-000123. */
    public function nextNumber(string $modelClass, string $column, string $prefix): string
    {
        $last = $modelClass::query()->lockForUpdate()->orderByDesc('id')->value('id') ?? 0;
        $n = $last + 1;
        do {
            $number = sprintf('%s-%06d', $prefix, $n++);
        } while ($modelClass::where($column, $number)->exists());

        return $number;
    }
}
