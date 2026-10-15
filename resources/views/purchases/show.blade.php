@extends('layouts.app')
@section('title', 'Stock in '.$purchase->reference_no)

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4 no-print">
    <div>
        <h2 class="page-title">Stock in {{ $purchase->reference_no }}</h2>
        <p class="page-subtitle">{{ $purchase->purchase_date->format('d M Y') }} · {{ $purchase->supplier?->name ?? 'No supplier' }} · recorded by {{ $purchase->user?->name }}</p>
    </div>
    <button onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="w-4 h-4" /> Print</button>
</div>
@include('partials.report-header', ['title' => 'Goods Received Note', 'subtitle' => $purchase->reference_no.' · '.($purchase->supplier?->name ?? '')])

<div class="grid lg:grid-cols-3 gap-4">
    <div class="card lg:col-span-2 overflow-x-auto">
        <table class="table">
            <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Sale price</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach($purchase->items as $item)
                <tr>
                    <td class="font-medium"><a href="{{ route('products.show', $item->product_id) }}" class="hover:underline">{{ $item->product?->display_name }}</a></td>
                    <td class="font-mono text-xs">{{ $item->batch_no }}</td>
                    <td>{{ $item->expiry_date?->format('M Y') ?? '—' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ money($item->unit_cost) }}</td>
                    <td class="num">{{ money($item->sale_price) }}</td>
                    <td class="num font-medium">{{ money($item->line_total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="space-y-4">
        <div class="card card-body text-sm space-y-2">
            <div class="flex justify-between"><span class="text-slate-500">Supplier invoice</span><span>{{ $purchase->supplier_invoice_no ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>{{ money($purchase->subtotal) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Discount</span><span>− {{ money($purchase->discount) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Tax / charges</span><span>{{ money($purchase->tax) }}</span></div>
            <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Total</span><span>{{ money($purchase->total) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Paid</span><span>{{ money($purchase->paid_amount) }}</span></div>
            <div class="flex justify-between font-semibold {{ $purchase->due_amount > 0 ? 'text-red-600' : 'text-emerald-700' }}"><span>Due</span><span>{{ money($purchase->due_amount) }}</span></div>
        </div>
        @if($purchase->due_amount > 0)
            <form method="POST" action="{{ route('purchases.payment', $purchase) }}" class="card card-body space-y-2 no-print">
                @csrf
                <label class="label">Record a payment to supplier</label>
                <div class="flex gap-2"><input type="number" step="0.01" min="0.01" max="{{ $purchase->due_amount }}" name="amount" value="{{ $purchase->due_amount }}" class="input"><button class="btn btn-primary">Save</button></div>
            </form>
        @endif
        @if($purchase->notes)<div class="card card-body text-sm"><span class="text-slate-500">Notes:</span> {{ $purchase->notes }}</div>@endif
    </div>
</div>
@endsection
