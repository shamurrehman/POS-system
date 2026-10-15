<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Category;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    /** Batch-level view of everything on the shelves. */
    public function index(Request $request, ReportService $reports)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'show' => ['nullable', Rule::in(['in_stock', 'all', 'expired', 'near_expiry'])],
        ]);
        $show = $filters['show'] ?? 'in_stock';

        $batches = Batch::with(['product.category', 'supplier'])
            ->whereHas('product', fn ($q) => $q->search($filters['q'] ?? null)
                ->when($filters['category_id'] ?? null, fn ($w, $v) => $w->where('category_id', $v)))
            ->when($show === 'in_stock', fn ($q) => $q->inStock())
            ->when($show === 'expired', fn ($q) => $q->inStock()->expired())
            ->when($show === 'near_expiry', fn ($q) => $q->inStock()->nearExpiry())
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->paginate(40)->withQueryString();

        return view('inventory.index', [
            'batches' => $batches,
            'valuation' => $reports->inventoryValue(),
            'categories' => Category::orderBy('name')->get(),
            'filters' => $filters + ['show' => $show],
        ]);
    }

    /** Every stock movement: purchases, sales, returns, write-offs, adjustments. */
    public function history(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(StockMovement::TYPES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $movements = StockMovement::with(['product', 'batch', 'user'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->whereHas('product', fn ($p) => $p->withTrashed()->search($v))
                ->orWhere('reference_no', 'like', "%{$v}%")))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->latest('id')
            ->paginate(40)->withQueryString();

        return view('inventory.history', [
            'movements' => $movements,
            'types' => StockMovement::TYPES,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
