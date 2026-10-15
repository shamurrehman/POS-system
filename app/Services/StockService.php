<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Single entry point for every stock change. Batch quantities are the source of truth;
 * products.stock_quantity is recomputed from them after each change and every change is
 * written to stock_movements, which gives a complete inventory history.
 *
 * Callers must wrap these calls in a DB transaction.
 */
class StockService
{
    /**
     * Batch number for stock that came without one (surgical items, cosmetics, loose medicines).
     * Stock with the same expiry is grouped together; stock with no expiry shares one batch.
     */
    public static function defaultBatchNo(?string $expiry): string
    {
        return $expiry ? 'NB-EXP'.date('Ymd', strtotime($expiry)) : 'NB-NOEXP';
    }

    /** Receive stock into a batch (creating it, or topping up an existing batch with the same number). */
    public function receive(Product $product, array $data, string $type, ?Model $reference = null, ?string $referenceNo = null, ?string $note = null): Batch
    {
        $data['batch_no'] = trim((string) ($data['batch_no'] ?? '')) ?: self::defaultBatchNo($data['expiry_date'] ?? null);
        $qty = (int) $data['quantity'];
        if ($qty <= 0) {
            throw new RuntimeException('Quantity must be greater than zero.');
        }

        $batch = Batch::query()
            ->where('product_id', $product->id)
            ->where('batch_no', $data['batch_no'])
            ->lockForUpdate()
            ->first();

        if ($batch) {
            $batch->quantity += $qty;
            $batch->initial_quantity += $qty;
            $batch->fill(array_filter([
                'expiry_date' => $data['expiry_date'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'sale_price' => $data['sale_price'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_id' => $data['purchase_id'] ?? null,
            ], fn ($v) => $v !== null));
            $batch->received_at = now();
            $batch->save();
        } else {
            $batch = Batch::create([
                'product_id' => $product->id,
                'batch_no' => $data['batch_no'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'quantity' => $qty,
                'initial_quantity' => $qty,
                'purchase_price' => $data['purchase_price'] ?? $product->purchase_price,
                'sale_price' => $data['sale_price'] ?? $product->sale_price,
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_id' => $data['purchase_id'] ?? null,
                'received_at' => now(),
            ]);
        }

        $this->record($product, $batch, $type, $qty, (float) $batch->purchase_price, $reference, $referenceNo, $note);

        return $batch;
    }

    /**
     * Take stock out of a product using first-expiry-first-out across non-expired batches.
     *
     * @return list<array{batch: Batch, quantity: int}>
     */
    public function deductFefo(Product $product, int $qty, string $type, ?Model $reference = null, ?string $referenceNo = null, ?string $note = null, ?int $preferredBatchId = null): array
    {
        $batches = $product->sellableBatches()->lockForUpdate()->get();

        if ($preferredBatchId) {
            // A scanned pack tells us exactly which batch is in the customer's hand; use it first.
            $batches = $batches->sortBy(fn (Batch $b) => $b->id === $preferredBatchId ? 0 : 1)->values();
        }

        $available = (int) $batches->sum('quantity');
        if ($available < $qty) {
            throw new RuntimeException("Not enough stock for {$product->display_name}. Available: {$available}, requested: {$qty}.");
        }

        $allocations = [];
        $remaining = $qty;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $batch->quantity);
            $this->takeFromBatch($product, $batch, $take, $type, $reference, $referenceNo, $note);
            $allocations[] = ['batch' => $batch, 'quantity' => $take];
            $remaining -= $take;
        }

        return $allocations;
    }

    /** Remove a quantity from one specific batch (write-offs, adjustments). Expired batches are allowed here. */
    public function deductFromBatch(Batch $batch, int $qty, string $type, ?Model $reference = null, ?string $referenceNo = null, ?string $note = null): void
    {
        $batch = Batch::query()->lockForUpdate()->findOrFail($batch->id);
        if ($qty <= 0 || $qty > $batch->quantity) {
            throw new RuntimeException("Invalid quantity. Batch {$batch->batch_no} has {$batch->quantity} in stock.");
        }

        $this->takeFromBatch($batch->product, $batch, $qty, $type, $reference, $referenceNo, $note);
    }

    /** Put a quantity back into a specific batch (customer returns, positive adjustments). */
    public function addToBatch(Batch $batch, int $qty, string $type, ?Model $reference = null, ?string $referenceNo = null, ?string $note = null): void
    {
        if ($qty <= 0) {
            throw new RuntimeException('Quantity must be greater than zero.');
        }
        $batch = Batch::query()->lockForUpdate()->findOrFail($batch->id);
        $batch->increment('quantity', $qty);

        $this->record($batch->product, $batch, $type, $qty, (float) $batch->purchase_price, $reference, $referenceNo, $note);
    }

    public function syncProductStock(Product $product): int
    {
        $total = (int) Batch::where('product_id', $product->id)->sum('quantity');
        Product::withTrashed()->whereKey($product->id)->update(['stock_quantity' => $total]);
        $product->stock_quantity = $total;

        return $total;
    }

    private function takeFromBatch(Product $product, Batch $batch, int $qty, string $type, ?Model $reference, ?string $referenceNo, ?string $note): void
    {
        $batch->decrement('quantity', $qty);
        $this->record($product, $batch, $type, -$qty, (float) $batch->purchase_price, $reference, $referenceNo, $note);
    }

    private function record(Product $product, Batch $batch, string $type, int $signedQty, float $unitCost, ?Model $reference, ?string $referenceNo, ?string $note): void
    {
        $balance = $this->syncProductStock($product);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'type' => $type,
            'quantity' => $signedQty,
            'balance_after' => $balance,
            'unit_cost' => $unitCost,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reference_no' => $referenceNo,
            'note' => $note ? mb_substr($note, 0, 250) : null,
            'user_id' => auth()->id(),
        ]);
    }
}
