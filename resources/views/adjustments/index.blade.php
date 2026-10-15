@extends('layouts.app')
@section('title', 'Adjustments & Write-offs')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">Stock adjustments & write-offs</h2>
        <p class="page-subtitle">Expired or damaged stock, returns to supplier, and stock-count corrections.</p>
    </div>
    <a href="{{ route('adjustments.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New adjustment</a>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Date</th><th>Medicine</th><th>Batch</th><th>Type</th><th class="num">Qty</th><th class="num">Value at cost</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
        @forelse($movements as $m)
            <tr>
                <td class="whitespace-nowrap">{{ $m->created_at->format('d M Y h:i A') }}</td>
                <td><a href="{{ route('products.show', $m->product_id) }}" class="font-medium hover:underline">{{ $m->product?->display_name }}</a></td>
                <td class="font-mono text-xs">{{ $m->batch?->batch_no }}</td>
                <td>@include('inventory._type', ['type' => $m->type, 'label' => $m->type_label])</td>
                <td class="num font-semibold {{ $m->quantity > 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                <td class="num">{{ money(abs($m->quantity) * $m->unit_cost) }}</td>
                <td class="text-sm">{{ $m->note }}</td>
                <td>{{ $m->user?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-slate-400 py-10">No adjustments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $movements->links() }}</div>
@endsection
