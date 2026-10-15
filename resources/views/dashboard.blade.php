@extends('layouts.app')
@section('title', 'Dashboard')

@php
    $trend = function (float $now, float $before) {
        if ($before <= 0) return null;
        return round(($now - $before) / $before * 100, 1);
    };
    $isManager = auth()->user()->isManagerOrAdmin();
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <h2 class="page-title">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}</h2>
        <p class="page-subtitle">{{ now()->format('l, d F Y') }}: here's how the pharmacy is doing.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('pos') }}" class="btn btn-primary"><x-icon name="cart" class="w-4 h-4" /> Open POS</a>
        @if($isManager)<a href="{{ route('purchases.create') }}" class="btn btn-secondary"><x-icon name="inbox" class="w-4 h-4" /> Stock In</a>@endif
    </div>
</div>

{{-- Alert strip --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
    @foreach([
        ['near_expiry', 'Near expiry', 'amber', 'clock', 'batches'],
        ['expired', 'Expired on shelf', 'red', 'warning', 'batches'],
        ['low', 'Low stock', 'amber', 'box', 'products'],
        ['out', 'Out of stock', 'red', 'box', 'products'],
        ['new_arrivals', 'New arrivals', 'blue', 'sparkles', 'batches'],
    ] as [$key, $label, $color, $icon, $unit])
        <a href="{{ route('alerts', ['tab' => $key === 'new_arrivals' ? 'new' : $key]) }}"
           class="stat flex items-center gap-3 hover:ring-2 hover:ring-slate-200 transition">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center
                {{ $color === 'red' ? 'bg-red-100 text-red-600' : ($color === 'amber' ? 'bg-amber-100 text-amber-600' : 'bg-sky-100 text-sky-600') }}">
                <x-icon :name="$icon" />
            </div>
            <div>
                <div class="text-xl font-bold tabular-nums {{ $alerts[$key] > 0 && $color === 'red' ? 'text-red-600' : '' }}">{{ $alerts[$key] }}</div>
                <div class="text-xs text-slate-500">{{ $label }}</div>
            </div>
        </a>
    @endforeach
</div>

{{-- KPIs --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    @php $t = $trend($today['net_sales'], $yesterday['net_sales']); @endphp
    <div class="stat">
        <div class="stat-label">Today's sales</div>
        <div class="stat-value">{{ money($today['net_sales']) }}</div>
        <div class="stat-hint">{{ $today['invoices'] }} invoices · {{ $today['items_sold'] }} items
            @if($t !== null)<span class="{{ $t >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $t >= 0 ? '▲' : '▼' }} {{ abs($t) }}% vs yesterday</span>@endif
        </div>
    </div>
    @if($isManager)
        <div class="stat">
            <div class="stat-label">Today's profit</div>
            <div class="stat-value text-emerald-700">{{ money($today['profit']) }}</div>
            <div class="stat-hint">Margin {{ $today['margin'] }}% · Returns {{ money($today['returns']) }}</div>
        </div>
        @php $tm = $trend($month['net_sales'], $lastMonth['net_sales']); @endphp
        <div class="stat">
            <div class="stat-label">This month's sales</div>
            <div class="stat-value">{{ money($month['net_sales']) }}</div>
            <div class="stat-hint">Profit {{ money($month['profit']) }}
                @if($tm !== null)<span class="{{ $tm >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $tm >= 0 ? '▲' : '▼' }} {{ abs($tm) }}% vs last month</span>@endif
            </div>
        </div>
        <div class="stat">
            <div class="stat-label">Stock value (cost)</div>
            <div class="stat-value">{{ money($inventory['cost']) }}</div>
            <div class="stat-hint">Retail {{ money($inventory['retail']) }} · {{ number_format($inventory['units']) }} units</div>
        </div>
    @else
        <div class="stat">
            <div class="stat-label">Average bill</div>
            <div class="stat-value">{{ money($today['avg_invoice']) }}</div>
            <div class="stat-hint">Today</div>
        </div>
    @endif
</div>

@if($isManager)
<div class="grid lg:grid-cols-3 gap-4 mb-5 items-start">
    <div class="card lg:col-span-2">
        <div class="card-header"><span class="card-title">Last 30 days</span><span class="text-xs text-slate-500">Net sales & profit per day</span></div>
        <div class="card-body"><div class="h-72"><canvas id="chartDaily"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Top sellers this month</span><a href="{{ route('reports.products') }}" class="text-xs text-brand-700 hover:underline">All</a></div>
        <table class="table">
            <tbody>
            @forelse($topProducts as $p)
                <tr>
                    <td class="!py-2"><div class="font-medium text-slate-800 truncate max-w-[180px]">{{ $p->product_name }}</div><div class="text-xs text-slate-500">{{ (int) $p->qty }} sold</div></td>
                    <td class="num !py-2">{{ money($p->revenue) }}</td>
                </tr>
            @empty
                <tr><td class="text-center text-slate-400 py-8">No sales yet this month.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-5 items-start">
    <div class="card lg:col-span-2">
        <div class="card-header"><span class="card-title">{{ now()->year }} by month</span><a href="{{ route('reports.yearly') }}" class="text-xs text-brand-700 hover:underline">Yearly report</a></div>
        <div class="card-body"><div class="h-64"><canvas id="chartMonthly"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Expiring soon</span><a href="{{ route('alerts', ['tab' => 'near_expiry']) }}" class="text-xs text-brand-700 hover:underline">All</a></div>
        <ul class="divide-y divide-slate-100">
            @forelse($nearExpiryList as $b)
                <li class="px-5 py-2.5 flex justify-between gap-2 text-sm">
                    <div class="min-w-0"><div class="font-medium truncate">{{ $b->product?->display_name }}</div><div class="text-xs text-slate-500">Batch {{ $b->batch_no }} · {{ $b->quantity }} left</div></div>
                    <span class="badge {{ $b->daysToExpiry() <= 30 ? 'badge-red' : 'badge-amber' }} self-center">{{ $b->expiry_date->format('d M Y') }}</span>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-400">Nothing expiring soon.</li>
            @endforelse
        </ul>
        <div class="card-header border-t border-b-0"><span class="card-title">Low stock</span><a href="{{ route('alerts', ['tab' => 'low']) }}" class="text-xs text-brand-700 hover:underline">All</a></div>
        <ul class="divide-y divide-slate-100">
            @forelse($lowStockList as $p)
                <li class="px-5 py-2.5 flex justify-between text-sm"><a href="{{ route('products.show', $p) }}" class="truncate hover:underline">{{ $p->display_name }}</a><span class="badge badge-amber">{{ $p->stock_quantity }} / {{ $p->reorder_level }}</span></li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-400">All stock levels are healthy.</li>
            @endforelse
        </ul>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><span class="card-title">Recent sales</span><a href="{{ route('sales.index') }}" class="text-xs text-brand-700 hover:underline">View all</a></div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Time</th><th>Customer</th><th>Cashier</th><th>Payment</th><th class="num">Total</th><th></th></tr></thead>
            <tbody>
            @forelse($recentSales as $s)
                <tr>
                    <td><a href="{{ route('sales.show', $s) }}" class="font-medium text-brand-700 hover:underline">{{ $s->invoice_no }}</a></td>
                    <td>{{ $s->created_at->format('d M, h:i A') }}</td>
                    <td>{{ $s->customer_name ?: 'Walk-in' }}</td>
                    <td>{{ $s->user?->name }}</td>
                    <td><span class="badge badge-slate">{{ $s->payment_label }}</span></td>
                    <td class="num font-medium">{{ money($s->total) }}</td>
                    <td class="text-right">@include('sales._status', ['sale' => $s])</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400 py-8">No sales yet. <a href="{{ route('pos') }}" class="text-brand-700 underline">Make the first sale</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@if($isManager)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fmt = (v) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 });
    const opts = {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } }, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: {{ setting('currency_symbol') }} ${fmt(c.raw)}` } } },
        scales: { y: { beginAtZero: true, ticks: { callback: fmt }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
    };
    const daily = @json($chartDaily);
    new Chart(document.getElementById('chartDaily'), {
        type: 'bar',
        data: { labels: daily.labels, datasets: [
            { label: 'Net sales', data: daily.sales, backgroundColor: '#10b981', borderRadius: 4 },
            { label: 'Profit', data: daily.profit, type: 'line', borderColor: '#0f172a', backgroundColor: '#0f172a', tension: .3, pointRadius: 2 },
        ] },
        options: opts,
    });
    const monthly = @json($chartMonthly);
    new Chart(document.getElementById('chartMonthly'), {
        type: 'bar',
        data: { labels: monthly.labels, datasets: [
            { label: 'Net sales', data: monthly.sales, backgroundColor: '#059669', borderRadius: 4 },
            { label: 'Profit', data: monthly.profit, backgroundColor: '#94a3b8', borderRadius: 4 },
        ] },
        options: opts,
    });
});
</script>
@endpush
@endif
