<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StockAdjustmentController extends Controller
{
    public const TYPES = ['adjustment_in', 'adjustment_out', 'expired', 'damaged', 'supplier_return'];

    public function index(Request $request)
    {
        $movements = StockMovement::with(['product', 'batch', 'user'])
            ->whereIn('type', self::TYPES)
            ->latest('id')
            ->paginate(30);

        return view('adjustments.index', compact('movements'));
    }

    public function create(Request $request)
    {
        $product = $request->integer('product_id') ? Product::with(['batches' => fn ($q) => $q->orderBy('expiry_date')])->find($request->integer('product_id')) : null;

        return view('adjustments.create', [
            'product' => $product,
            'batchId' => $request->integer('batch_id') ?: null,
            'types' => array_intersect_key(StockMovement::TYPES, array_flip(self::TYPES)),
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'type' => ['required', Rule::in(self::TYPES)],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['required', 'string', 'max:250'],
        ], ['note.required' => 'Please write a reason for this adjustment.']);

        $batch = Batch::with('product')->findOrFail($data['batch_id']);

        try {
            DB::transaction(function () use ($data, $batch, $stock) {
                if ($data['type'] === 'adjustment_in') {
                    $stock->addToBatch($batch, $data['quantity'], 'adjustment_in', null, null, $data['note']);
                } else {
                    $stock->deductFromBatch($batch, $data['quantity'], $data['type'], null, null, $data['note']);
                }
            });
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        ActivityLog::record('stock.adjusted', StockMovement::TYPES[$data['type']]." of {$data['quantity']} × {$batch->product->name} (batch {$batch->batch_no}): {$data['note']}", $batch->product);

        return redirect()->route('products.show', $batch->product_id)->with('success', 'Stock updated.');
    }
}
