@extends('layouts.app')
@section('title', 'Daily Report')

@section('content')
@include('reports._toolbar', [
    'title' => 'Daily sales report',
    'subtitle' => $date->format('l, d F Y'),
    'filters' => '<div><label class="label">Date</label><input type="date" name="date" value="'.e($date->toDateString()).'" max="'.today()->toDateString().'" class="input"></div>',
])
@include('partials.report-header', ['title' => 'Daily Sales Report', 'subtitle' => $date->format('l, d F Y')])

@include('reports._summary')

<div class="grid md:grid-cols-3 gap-4 mb-4">
    <div class="card">
        <div class="card-header"><span class="card-title">Cash drawer / payment methods</span></div>
        <div class="card-body text-sm space-y-2">
            @forelse($payments as $p)
                <div class="flex justify-between"><span>{{ \App\Models\Sale::PAYMENT_METHODS[$p->payment_method] ?? $p->payment_method }} ({{ $p->invoices }})</span><b>{{ money($p->total) }}</b></div>
            @empty <div class="text-slate-400">No sales.</div> @endforelse
            @if($summary['returns'] > 0)
                <div class="flex justify-between text-red-600 border-t pt-2"><span>Refunds paid out</span><b>− {{ money($summary['returns']) }}</b></div>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">By cashier</span></div>
        <div class="card-body text-sm space-y-2">
            @forelse($byCashier as $name => $c)
                <div class="flex justify-between"><span>{{ $name }} ({{ $c['count'] }})</span><b>{{ money($c['total']) }}</b></div>
            @empty <div class="text-slate-400">No sales.</div> @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Top items today</span></div>
        <div class="card-body text-sm space-y-1">
            @forelse($topProducts->take(8) as $p)
                <div class="flex justify-between gap-2"><span class="truncate">{{ $p->product_name }} × {{ (int) $p->qty }}</span><b>{{ money($p->revenue) }}</b></div>
            @empty <div class="text-slate-400">No sales.</div> @endforelse
        </div>
    </div>
</div>

<div class="card overflow-x-auto mb-4">
    <div class="card-header"><span class="card-title">Invoices ({{ $sales->count() }})</span></div>
    <table class="table">
        <thead><tr><th>Invoice</th><th>Time</th><th>Customer</th><th>Cashier</th><th>Payment</th><th class="num">Discount</th><th class="num">Total</th><th class="num">Profit</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($sales as $s)
            <tr>
                <td><a href="{{ route('sales.show', $s) }}" class="text-brand-700 hover:underline">{{ $s->invoice_no }}</a></td>
                <td>{{ $s->created_at->format('h:i A') }}</td>
                <td>{{ $s->customer_name ?: 'Walk-in' }}</td>
                <td>{{ $s->user?->name }}</td>
                <td>{{ $s->payment_label }}</td>
                <td class="num">{{ money($s->discount) }}</td>
                <td class="num font-medium">{{ money($s->total) }}</td>
                <td class="num text-emerald-700">{{ money($s->total - $s->tax - $s->cost_total) }}</td>
                <td>@include('sales._status', ['sale' => $s])</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-slate-400 py-8">No sales on this day.</td></tr>
        @endforelse
        </tbody>
        @if($sales->isNotEmpty())
            <tfoot><tr><td colspan="5">Total</td><td class="num">{{ money($sales->sum('discount')) }}</td><td class="num">{{ money($sales->sum('total')) }}</td><td class="num">{{ money($sales->sum(fn ($s) => $s->total - $s->tax - $s->cost_total)) }}</td><td></td></tr></tfoot>
        @endif
    </table>
</div>

@if($returns->isNotEmpty())
<div class="card overflow-x-auto">
    <div class="card-header"><span class="card-title">Returns ({{ $returns->count() }})</span></div>
    <table class="table">
        <thead><tr><th>Return</th><th>Time</th><th>Original invoice</th><th>By</th><th>Reason</th><th class="num">Refund</th></tr></thead>
        <tbody>
        @foreach($returns as $r)
            <tr><td>{{ $r->return_no }}</td><td>{{ $r->created_at->format('h:i A') }}</td><td>{{ $r->sale?->invoice_no }}</td><td>{{ $r->user?->name }}</td><td>{{ $r->reason }}</td><td class="num">{{ money($r->total) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
