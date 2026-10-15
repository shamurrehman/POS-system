<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $query = Sale::with('user')
            ->when(! $user->isManagerOrAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('invoice_no', 'like', "%{$v}%")
                ->orWhere('customer_name', 'like', "%{$v}%")
                ->orWhere('customer_phone', 'like', "%{$v}%")))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        $totals = (clone $query)->reorder()->selectRaw('COUNT(*) as n, COALESCE(SUM(total),0) as total, COALESCE(SUM(returned_amount),0) as returned')->first();

        return view('sales.index', [
            'sales' => $query->latest()->paginate(25)->withQueryString(),
            'totals' => $totals,
            'users' => $user->isManagerOrAdmin() ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Sale $sale)
    {
        $this->authorizeView($request, $sale);
        $sale->load(['items', 'user', 'returns.user', 'returns.items.saleItem']);

        return view('sales.show', compact('sale'));
    }

    public function receipt(Request $request, Sale $sale)
    {
        $this->authorizeView($request, $sale);
        $sale->load(['items', 'user']);

        return view('sales.receipt', [
            'sale' => $sale,
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    public function returnForm(Sale $sale)
    {
        $sale->load('items');
        abort_if($sale->status === 'returned', 404);

        return view('sales.return', compact('sale'));
    }

    public function returnStore(Request $request, Sale $sale, SaleService $sales)
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
            'restock' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:250'],
        ]);

        $return = $sales->processReturn($sale, $data['quantities'], $request->boolean('restock'), $data['reason'] ?? null, $request->user());

        return redirect()->route('sales.show', $sale)
            ->with('success', "Return {$return->return_no} saved. Refund the customer ".money($return->total).'.');
    }

    private function authorizeView(Request $request, Sale $sale): void
    {
        $user = $request->user();
        abort_unless($user->isManagerOrAdmin() || $sale->user_id === $user->id, 403);
    }
}
