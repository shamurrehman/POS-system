@extends('layouts.app')
@section('title', 'Medicines')

@section('content')
@php $isManager = auth()->user()->isManagerOrAdmin(); @endphp
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">Medicines & Products</h2>
        <p class="page-subtitle">{{ number_format($products->total()) }} products</p>
    </div>
    @if($isManager)
        <div class="flex gap-2">
            <a href="{{ route('products.labels') }}" class="btn btn-secondary"><x-icon name="printer" class="w-4 h-4" /> Barcode labels</a>
            <a href="{{ route('products.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> Add medicine</a>
        </div>
    @endif
</div>

<form class="card card-body mb-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
    <div class="col-span-2"><label class="label">Search</label><input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Name, generic, barcode or manufacturer" autofocus></div>
    <div><label class="label">Category</label>
        <select name="category_id" class="input"><option value="">All</option>
            @foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div><label class="label">Stock</label>
        <select name="stock" class="input"><option value="">All</option>
            <option value="in" @selected(($filters['stock'] ?? '') === 'in')>Healthy</option>
            <option value="low" @selected(($filters['stock'] ?? '') === 'low')>Low stock</option>
            <option value="out" @selected(($filters['stock'] ?? '') === 'out')>Out of stock</option>
        </select>
    </div>
    <div class="flex gap-2"><button class="btn btn-dark flex-1">Filter</button><a href="{{ route('products.index') }}" class="btn btn-secondary">Reset</a></div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Medicine</th><th>Category</th><th>Barcode</th><th>Rack</th>@if($isManager)<th class="num">Cost</th>@endif<th class="num">Price</th><th>Stock</th><th></th></tr></thead>
        <tbody>
        @forelse($products as $p)
            <tr class="{{ $p->is_active ? '' : 'opacity-50' }}">
                <td>
                    <a href="{{ route('products.show', $p) }}" class="font-medium text-slate-800 hover:text-brand-700">{{ $p->display_name }}</a>
                    @if($p->requires_prescription)<span class="badge badge-purple">Rx</span>@endif
                    @unless($p->is_active)<span class="badge badge-slate">Inactive</span>@endunless
                    <div class="text-xs text-slate-500">{{ $p->generic_name }} @if($p->manufacturer)· {{ $p->manufacturer }}@endif @if($p->dosage_form)· {{ $p->dosage_form }}@endif</div>
                </td>
                <td>{{ $p->category?->name ?? '—' }}</td>
                <td class="text-xs font-mono">{{ $p->barcode }}</td>
                <td>{{ $p->rack_location ?? '—' }}</td>
                @if($isManager)<td class="num">{{ money($p->purchase_price) }}</td>@endif
                <td class="num font-medium">{{ money($p->sale_price) }}</td>
                <td>@include('products._stock-badge', ['product' => $p])</td>
                <td class="text-right whitespace-nowrap">
                    <a href="{{ route('products.show', $p) }}" class="btn btn-secondary btn-sm">View</a>
                    @if($isManager)<a href="{{ route('products.edit', $p) }}" class="btn btn-secondary btn-sm">Edit</a>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-slate-400 py-10">No products found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
