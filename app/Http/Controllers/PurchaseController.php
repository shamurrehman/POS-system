<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'integer'],
            'payment_status' => ['nullable', 'in:paid,partial,unpaid'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Purchase::query()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('purchase_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('purchase_date', '<=', $v))
            ->when($filters['supplier_id'] ?? null, fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('reference_no', 'like', "%{$v}%")->orWhere('supplier_invoice_no', 'like', "%{$v}%")));

        $totals = (clone $query)->selectRaw('COALESCE(SUM(total),0) as total, COALESCE(SUM(paid_amount),0) as paid')->first();

        return view('purchases.index', [
            'purchases' => $query->with(['supplier', 'user'])->withCount('items')->latest('purchase_date')->latest('id')->paginate(25)->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'totals' => $totals,
        ]);
    }

    public function create()
    {
        return view('purchases.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, PurchaseService $service)
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'update_prices' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'items.*.sale_price' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ], [
            'items.required' => 'Add at least one medicine to the stock-in.',
        ]);
        $data['update_prices'] = $request->boolean('update_prices', true);

        $purchase = $service->create($data, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('purchases.show', $purchase)]);
        }

        return redirect()->route('purchases.show', $purchase)->with('success', "Stock-in {$purchase->reference_no} saved.");
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'user', 'items.product']);

        return view('purchases.show', compact('purchase'));
    }

    public function payment(Request $request, Purchase $purchase)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        $paid = min((float) $purchase->total, (float) $purchase->paid_amount + (float) $data['amount']);

        $purchase->update([
            'paid_amount' => $paid,
            'payment_status' => $paid >= (float) $purchase->total ? 'paid' : 'partial',
        ]);
        ActivityLog::record('purchase.payment', "Payment of ".money($data['amount'])." on {$purchase->reference_no}", $purchase);

        return back()->with('success', 'Payment recorded.');
    }
}
