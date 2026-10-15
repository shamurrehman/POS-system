<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(private StockService $stock, private SaleService $sales) {}

    /**
     * Record a stock-in from a supplier. Each line creates (or tops up) a batch.
     *
     * @param  array{supplier_id:?int, supplier_invoice_no:?string, purchase_date:string, discount?:float, tax?:float, paid_amount?:?float, notes?:?string, update_prices?:bool, items: list<array{product_id:int, batch_no:string, expiry_date:?string, quantity:int, unit_cost:float, sale_price:float}>}  $data
     */
    public function create(array $data, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $subtotal = collect($data['items'])->sum(fn ($i) => round($i['quantity'] * $i['unit_cost'], 2));
            $discount = round((float) ($data['discount'] ?? 0), 2);
            $tax = round((float) ($data['tax'] ?? 0), 2);
            $total = round(max(0, $subtotal - $discount + $tax), 2);
            $paid = isset($data['paid_amount']) && $data['paid_amount'] !== null ? min($total, round((float) $data['paid_amount'], 2)) : $total;

            $purchase = Purchase::create([
                'reference_no' => $this->sales->nextNumber(Purchase::class, 'reference_no', 'PUR'),
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $paid,
                'payment_status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->id,
            ]);

            foreach ($data['items'] as $line) {
                $product = Product::lockForUpdate()->findOrFail($line['product_id']);

                $batch = $this->stock->receive($product, [
                    'batch_no' => $line['batch_no'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'quantity' => $line['quantity'],
                    'purchase_price' => $line['unit_cost'],
                    'sale_price' => $line['sale_price'],
                    'supplier_id' => $purchase->supplier_id,
                    'purchase_id' => $purchase->id,
                ], 'purchase', $purchase, $purchase->reference_no);

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'sale_price' => $line['sale_price'],
                    'line_total' => round($line['quantity'] * $line['unit_cost'], 2),
                ]);

                if ($data['update_prices'] ?? true) {
                    $product->update([
                        'purchase_price' => $line['unit_cost'],
                        'sale_price' => $line['sale_price'],
                    ]);
                }
            }

            ActivityLog::record('purchase.created', "Stock-in {$purchase->reference_no} for ".money($total), $purchase, $user->id);

            return $purchase;
        });
    }
}
