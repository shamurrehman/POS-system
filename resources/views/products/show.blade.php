@extends('layouts.app')
@section('title', $product->display_name)

@section('content')
@php $isManager = auth()->user()->isManagerOrAdmin(); @endphp
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">{{ $product->display_name }}
            @if($product->requires_prescription)<span class="badge badge-purple align-middle">Rx</span>@endif
            @include('products._stock-badge')
        </h2>
        <p class="page-subtitle">{{ $product->generic_name }} @if($product->manufacturer)· {{ $product->manufacturer }}@endif @if($product->category)· {{ $product->category->name }}@endif</p>
    </div>
    @if($isManager)
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('adjustments.create', ['product_id' => $product->id]) }}" class="btn btn-secondary"><x-icon name="adjust" class="w-4 h-4" /> Adjust stock</a>
            <a href="{{ route('products.labels', ['product_id' => $product->id]) }}" class="btn btn-secondary"><x-icon name="printer" class="w-4 h-4" /> Print labels</a>
            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">Edit</a>
        </div>
    @endif
</div>

<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-4">
    <div class="stat"><div class="stat-label">In stock</div><div class="stat-value">{{ $product->stock_quantity }}</div><div class="stat-hint">{{ $product->unit }}</div></div>
    <div class="stat"><div class="stat-label">Reorder at</div><div class="stat-value">{{ $product->reorder_level }}</div></div>
    <div class="stat"><div class="stat-label">Selling price</div><div class="stat-value text-xl">{{ money($product->sale_price) }}</div></div>
    @if($isManager)<div class="stat"><div class="stat-label">Cost price</div><div class="stat-value text-xl">{{ money($product->purchase_price) }}</div></div>@endif
    <div class="stat"><div class="stat-label">Sold (30 days)</div><div class="stat-value">{{ $soldLast30 }}</div><div class="stat-hint">≈ {{ round($soldLast30 / 30, 1) }} per day</div></div>
    <div class="stat"><div class="stat-label">Barcode</div><div class="mt-2 font-mono text-sm break-all">{{ $product->barcode ?? '—' }}</div><div class="stat-hint">Rack {{ $product->rack_location ?? '—' }}</div></div>
</div>

<div class="card mb-4 overflow-x-auto">
    <div class="card-header"><span class="card-title">Batches</span><span class="text-xs text-slate-500">Sold first-expiry-first-out automatically</span></div>
    <table class="table">
        <thead><tr><th>Batch</th><th>Expiry</th><th class="num">In stock</th><th class="num">Received</th>@if($isManager)<th class="num">Cost</th>@endif<th>Supplier</th><th>Received on</th><th></th></tr></thead>
        <tbody>
        @forelse($batches as $b)
            @php $days = $b->daysToExpiry(); @endphp
            <tr class="{{ $b->quantity <= 0 ? 'text-slate-400' : '' }}">
                <td class="font-mono text-xs">{{ $b->batch_no }}</td>
                <td>
                    @if($b->expiry_date)
                        {{ $b->expiry_date->format('d M Y') }}
                        @if($b->quantity > 0)
                            @if($days < 0)<span class="badge badge-red">Expired</span>
                            @elseif($days <= (int) setting('near_expiry_days'))<span class="badge badge-amber">{{ $days }} days</span>@endif
                        @endif
                    @else — @endif
                </td>
                <td class="num font-medium">{{ $b->quantity }}</td>
                <td class="num">{{ $b->initial_quantity }}</td>
                @if($isManager)<td class="num">{{ money($b->purchase_price) }}</td>@endif
                <td>{{ $b->supplier?->name ?? '—' }}</td>
                <td>{{ $b->received_at?->format('d M Y') }}</td>
                <td class="text-right">@if($isManager && $b->quantity > 0)<a href="{{ route('adjustments.create', ['product_id' => $product->id, 'batch_id' => $b->id]) }}" class="btn btn-secondary btn-sm">Adjust</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-slate-400 py-8">No stock received yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card overflow-x-auto">
    <div class="card-header"><span class="card-title">Stock history</span></div>
    <table class="table">
        <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Batch</th><th class="num">Change</th><th class="num">Balance</th><th>By</th><th>Note</th></tr></thead>
        <tbody>
        @forelse($movements as $m)
            <tr>
                <td class="whitespace-nowrap">{{ $m->created_at->format('d M Y h:i A') }}</td>
                <td>@include('inventory._type', ['type' => $m->type, 'label' => $m->type_label])</td>
                <td class="text-xs">{{ $m->reference_no }}</td>
                <td class="text-xs font-mono">{{ $m->batch?->batch_no }}</td>
                <td class="num font-semibold {{ $m->quantity > 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                <td class="num">{{ $m->balance_after }}</td>
                <td>{{ $m->user?->name }}</td>
                <td class="text-xs text-slate-500">{{ $m->note }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-slate-400 py-8">No movements yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $movements->links() }}</div>
@endsection
