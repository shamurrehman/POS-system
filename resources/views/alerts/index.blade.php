@extends('layouts.app')
@section('title', 'Alerts')

@section('content')
@php $isManager = auth()->user()->isManagerOrAdmin(); @endphp
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">Alerts</h2>
        <p class="page-subtitle">Near expiry = within {{ $nearDays }} days · New arrivals = received in the last {{ $newDays }} days. Change these in Settings.</p>
    </div>
    <button onclick="window.print()" class="btn btn-secondary no-print"><x-icon name="printer" class="w-4 h-4" /> Print list</button>
</div>

@php
    $tabs = [
        'near_expiry' => ['Near expiry', 'amber'],
        'expired' => ['Expired', 'red'],
        'low' => ['Low stock', 'amber'],
        'out' => ['Out of stock', 'red'],
        'new' => ['New arrivals', 'blue'],
    ];
@endphp
<div class="no-print flex flex-wrap gap-2 mb-4">
    @foreach($tabs as $key => [$label, $color])
        <a href="{{ route('alerts', ['tab' => $key]) }}" class="btn {{ $tab === $key ? 'btn-dark' : 'btn-secondary' }}">
            {{ $label }}
            <span class="badge {{ $counts[$key] ? ($color === 'red' ? 'badge-red' : ($color === 'amber' ? 'badge-amber' : 'badge-blue')) : 'badge-slate' }}">{{ $counts[$key] }}</span>
        </a>
    @endforeach
</div>
@include('partials.report-header', ['title' => $tabs[$tab][0].' report'])

<div class="card overflow-x-auto">
    @if(in_array($tab, ['near_expiry', 'expired', 'new']))
        <table class="table">
            <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th>{{ $tab === 'new' ? 'Received' : 'Days left' }}</th><th class="num">Qty</th>@if($isManager)<th class="num">Value at cost</th>@endif<th>Supplier</th><th class="no-print"></th></tr></thead>
            <tbody>
            @forelse($items as $b)
                @php $days = $b->daysToExpiry(); @endphp
                <tr>
                    <td><a href="{{ route('products.show', $b->product_id) }}" class="font-medium hover:underline">{{ $b->product?->display_name }}</a><div class="text-xs text-slate-500">Rack {{ $b->product?->rack_location ?? '—' }}</div></td>
                    <td class="font-mono text-xs">{{ $b->batch_no }}</td>
                    <td>{{ $b->expiry_date?->format('d M Y') ?? '—' }}</td>
                    <td>
                        @if($tab === 'new')
                            {{ $b->received_at?->format('d M Y h:i A') }}
                            @if($b->purchase)<div class="text-xs"><a href="{{ route('purchases.show', $b->purchase) }}" class="text-brand-700 hover:underline">{{ $b->purchase->reference_no }}</a></div>@endif
                        @elseif($days < 0)
                            <span class="badge badge-red">Expired {{ abs($days) }} days ago</span>
                        @else
                            <span class="badge {{ $days <= 30 ? 'badge-red' : 'badge-amber' }}">{{ $days }} days</span>
                        @endif
                    </td>
                    <td class="num font-medium">{{ $tab === 'new' ? $b->initial_quantity : $b->quantity }}</td>
                    @if($isManager)<td class="num">{{ money($b->quantity * $b->purchase_price) }}</td>@endif
                    <td>{{ $b->supplier?->name ?? '—' }}</td>
                    <td class="text-right no-print">
                        @if($isManager && $tab !== 'new' && $b->quantity > 0)
                            <a href="{{ route('adjustments.create', ['product_id' => $b->product_id, 'batch_id' => $b->id]) }}" class="btn btn-secondary btn-sm">{{ $tab === 'expired' ? 'Write off' : 'Return / adjust' }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-slate-400 py-10">Nothing here. All good.</td></tr>
            @endforelse
            </tbody>
        </table>
    @else
        <table class="table">
            <thead><tr><th>Medicine</th><th>Category</th><th class="num">In stock</th><th class="num">Reorder level</th><th class="num">Suggested order</th><th class="no-print"></th></tr></thead>
            <tbody>
            @forelse($items as $p)
                <tr>
                    <td><a href="{{ route('products.show', $p) }}" class="font-medium hover:underline">{{ $p->display_name }}</a><div class="text-xs text-slate-500">{{ $p->generic_name }} · {{ $p->manufacturer }}</div></td>
                    <td>{{ $p->category?->name ?? '—' }}</td>
                    <td class="num font-semibold {{ $p->stock_quantity <= 0 ? 'text-red-600' : 'text-amber-600' }}">{{ $p->stock_quantity }}</td>
                    <td class="num">{{ $p->reorder_level }}</td>
                    <td class="num">{{ max(0, $p->reorder_level * 2 - $p->stock_quantity) }} {{ $p->unit }}</td>
                    <td class="text-right no-print">@if($isManager)<a href="{{ route('purchases.create') }}" class="btn btn-secondary btn-sm">Stock in</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400 py-10">Nothing here. All good.</td></tr>
            @endforelse
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4 no-print">{{ $items->links() }}</div>
@endsection
