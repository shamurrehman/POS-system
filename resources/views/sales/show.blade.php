@extends('layouts.app')
@section('title', 'Invoice '.$sale->invoice_no)

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4 no-print">
    <div>
        <h2 class="page-title">Invoice {{ $sale->invoice_no }} @include('sales._status', ['sale' => $sale])</h2>
        <p class="page-subtitle">{{ $sale->created_at->format('l, d M Y h:i A') }} · by {{ $sale->user?->name }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('sales.receipt', ['sale' => $sale, 'print' => 1]) }}" target="_blank" class="btn btn-secondary"><x-icon name="printer" class="w-4 h-4" /> Print receipt</a>
        <button onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="w-4 h-4" /> Print A4 invoice</button>
        @if(auth()->user()->isManagerOrAdmin() && $sale->status !== 'returned')
            <a href="{{ route('sales.return', $sale) }}" class="btn btn-warning"><x-icon name="return" class="w-4 h-4" /> Return items</a>
        @endif
    </div>
</div>

@include('partials.report-header', ['title' => 'Sales Invoice', 'subtitle' => $sale->invoice_no])

<div class="grid lg:grid-cols-3 gap-4">
    <div class="card lg:col-span-2 overflow-x-auto">
        <table class="table">
            <thead><tr><th>#</th><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="num">Qty</th><th class="num">Returned</th><th class="num">Price</th><th class="num">Amount</th></tr></thead>
            <tbody>
            @foreach($sale->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="font-medium">{{ $item->product_name }}</td>
                    <td class="text-xs">{{ $item->batch_no }}</td>
                    <td class="text-xs">{{ $item->expiry_date?->format('M Y') }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num {{ $item->returned_quantity ? 'text-red-600' : 'text-slate-400' }}">{{ $item->returned_quantity ?: '—' }}</td>
                    <td class="num">{{ money($item->unit_price) }}</td>
                    <td class="num font-medium">{{ money($item->line_total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="space-y-4">
        <div class="card card-body space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>{{ money($sale->subtotal) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Discount</span><span>− {{ money($sale->discount) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Tax</span><span>{{ money($sale->tax) }}</span></div>
            <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Total</span><span>{{ money($sale->total) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Paid ({{ $sale->payment_label }})</span><span>{{ money($sale->paid_amount) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Change</span><span>{{ money($sale->change_amount) }}</span></div>
            @if($sale->returned_amount > 0)
                <div class="flex justify-between text-red-600"><span>Refunded</span><span>− {{ money($sale->returned_amount) }}</span></div>
                <div class="flex justify-between font-semibold"><span>Net</span><span>{{ money($sale->net_total) }}</span></div>
            @endif
            @if(auth()->user()->isManagerOrAdmin())
                <div class="flex justify-between border-t pt-2 no-print"><span class="text-slate-500">Cost of goods</span><span>{{ money($sale->cost_total) }}</span></div>
            @endif
        </div>
        <div class="card card-body text-sm space-y-1">
            <div><span class="text-slate-500">Customer:</span> {{ $sale->customer_name ?: 'Walk-in' }} {{ $sale->customer_phone }}</div>
            @if($sale->doctor_name)<div><span class="text-slate-500">Doctor / Rx:</span> {{ $sale->doctor_name }}</div>@endif
            @if($sale->notes)<div><span class="text-slate-500">Note:</span> {{ $sale->notes }}</div>@endif
        </div>
    </div>
</div>

@if($sale->returns->isNotEmpty())
<div class="card mt-4">
    <div class="card-header"><span class="card-title">Returns</span></div>
    <table class="table">
        <thead><tr><th>Return no</th><th>Date</th><th>By</th><th>Items</th><th>Reason</th><th class="num">Refund</th></tr></thead>
        <tbody>
        @foreach($sale->returns as $r)
            <tr>
                <td class="font-medium">{{ $r->return_no }}</td>
                <td>{{ $r->created_at->format('d M Y h:i A') }}</td>
                <td>{{ $r->user?->name }}</td>
                <td class="text-xs">@foreach($r->items as $ri){{ $ri->quantity }} × {{ $ri->saleItem?->product_name }}{{ $ri->restocked ? '' : ' (not restocked)' }}<br>@endforeach</td>
                <td>{{ $r->reason }}</td>
                <td class="num font-medium">{{ money($r->total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
