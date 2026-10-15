@extends('layouts.app')
@section('title', 'Stock Valuation')

@section('content')
@include('reports._toolbar', ['title' => 'Stock valuation', 'subtitle' => 'As of '.now()->format('d M Y h:i A')])
@include('partials.report-header', ['title' => 'Stock Valuation Report', 'subtitle' => 'As of '.now()->format('d M Y')])

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="stat"><div class="stat-label">Total value at cost</div><div class="stat-value">{{ money($valuation['cost']) }}</div></div>
    <div class="stat"><div class="stat-label">Total value at retail</div><div class="stat-value">{{ money($valuation['retail']) }}</div></div>
    <div class="stat"><div class="stat-label">Potential profit</div><div class="stat-value text-emerald-700">{{ money($valuation['potential_profit']) }}</div></div>
    <div class="stat"><div class="stat-label">Units</div><div class="stat-value">{{ number_format($valuation['units']) }}</div></div>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Medicine</th><th>Category</th><th class="num">Stock</th><th class="num">Reorder</th><th class="num">Cost value</th><th class="num">Sale price</th><th class="num">Retail value</th></tr></thead>
        <tbody>
        @foreach($rows as $p)
            <tr class="{{ $p->stock_quantity <= 0 ? 'text-slate-400' : '' }}">
                <td><a href="{{ route('products.show', $p) }}" class="font-medium hover:underline">{{ $p->display_name }}</a></td>
                <td>{{ $p->category?->name ?? '—' }}</td>
                <td class="num {{ $p->stock_status === 'low' ? 'text-amber-600 font-semibold' : '' }}">{{ $p->stock_quantity }} {{ $p->unit }}</td>
                <td class="num">{{ $p->reorder_level }}</td>
                <td class="num">{{ money($p->cost_value) }}</td>
                <td class="num">{{ money($p->sale_price) }}</td>
                <td class="num font-medium">{{ money($p->stock_quantity * $p->sale_price) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Total ({{ $rows->count() }} products)</td><td class="num">{{ number_format($rows->sum('stock_quantity')) }}</td><td></td>
            <td class="num">{{ money($rows->sum('cost_value')) }}</td><td></td><td class="num">{{ money($rows->sum(fn ($p) => $p->stock_quantity * $p->sale_price)) }}</td></tr></tfoot>
    </table>
</div>
@endsection
