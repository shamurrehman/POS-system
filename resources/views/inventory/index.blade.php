@extends('layouts.app')
@section('title', 'Stock & Batches')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">Complete inventory</h2>
        <p class="page-subtitle">Every batch on the shelves, earliest expiry first.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('reports.inventory') }}" class="btn btn-secondary">Valuation report</a>
        <a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="inbox" class="w-4 h-4" /> Stock in</a>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
    <div class="stat"><div class="stat-label">Stock value (cost)</div><div class="stat-value">{{ money($valuation['cost']) }}</div></div>
    <div class="stat"><div class="stat-label">Stock value (retail)</div><div class="stat-value">{{ money($valuation['retail']) }}</div></div>
    <div class="stat"><div class="stat-label">Potential profit</div><div class="stat-value text-emerald-700">{{ money($valuation['potential_profit']) }}</div></div>
    <div class="stat"><div class="stat-label">Units on hand</div><div class="stat-value">{{ number_format($valuation['units']) }}</div><div class="stat-hint">{{ $valuation['in_stock_products'] }} of {{ $valuation['products'] }} products in stock</div></div>
    <div class="stat"><div class="stat-label">Expired stock value</div><div class="stat-value {{ $valuation['expired_value'] > 0 ? 'text-red-600' : '' }}">{{ money($valuation['expired_value']) }}</div></div>
</div>

<form class="card card-body mb-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
    <div class="col-span-2"><label class="label">Search</label><input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Medicine, generic or barcode"></div>
    <div><label class="label">Category</label>
        <select name="category_id" class="input"><option value="">All</option>
            @foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div><label class="label">Show</label>
        <select name="show" class="input">
            @foreach(['in_stock' => 'In stock', 'near_expiry' => 'Near expiry', 'expired' => 'Expired', 'all' => 'All batches (incl. empty)'] as $k => $v)
                <option value="{{ $k }}" @selected($filters['show'] === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex gap-2"><button class="btn btn-dark flex-1">Filter</button><a href="{{ route('inventory.index') }}" class="btn btn-secondary">Reset</a></div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Medicine</th><th>Category</th><th>Batch</th><th>Expiry</th><th class="num">Qty</th><th class="num">Cost</th><th class="num">Value</th><th>Supplier</th><th>Received</th></tr></thead>
        <tbody>
        @forelse($batches as $b)
            @php $days = $b->daysToExpiry(); @endphp
            <tr>
                <td><a href="{{ route('products.show', $b->product_id) }}" class="font-medium hover:text-brand-700">{{ $b->product?->display_name }}</a><div class="text-xs text-slate-500">{{ $b->product?->generic_name }}</div></td>
                <td>{{ $b->product?->category?->name ?? '—' }}</td>
                <td class="font-mono text-xs">{{ $b->batch_no }}</td>
                <td class="whitespace-nowrap">
                    {{ $b->expiry_date?->format('d M Y') ?? '—' }}
                    @if($days !== null && $b->quantity > 0)
                        @if($days < 0)<span class="badge badge-red">Expired</span>
                        @elseif($days <= (int) setting('near_expiry_days'))<span class="badge badge-amber">{{ $days }}d</span>@endif
                    @endif
                </td>
                <td class="num font-medium">{{ $b->quantity }}</td>
                <td class="num">{{ money($b->purchase_price) }}</td>
                <td class="num">{{ money($b->quantity * $b->purchase_price) }}</td>
                <td>{{ $b->supplier?->name ?? '—' }}</td>
                <td>{{ $b->received_at?->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-slate-400 py-10">No batches match.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $batches->links() }}</div>
@endsection
