@extends('layouts.app')
@section('title', 'Stock History')

@section('content')
<div class="mb-4">
    <h2 class="page-title">Stock history</h2>
    <p class="page-subtitle">Full audit trail of every unit that came in or went out.</p>
</div>

<form class="card card-body mb-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
    <div class="col-span-2"><label class="label">Search</label><input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Medicine or reference no"></div>
    <div><label class="label">Type</label>
        <select name="type" class="input"><option value="">All</option>
            @foreach($types as $k => $v)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div><label class="label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
    <div class="flex gap-2"><button class="btn btn-dark flex-1">Filter</button><a href="{{ route('inventory.history') }}" class="btn btn-secondary">Reset</a></div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Date</th><th>Medicine</th><th>Type</th><th>Reference</th><th>Batch</th><th class="num">Change</th><th class="num">Balance</th><th>By</th><th>Note</th></tr></thead>
        <tbody>
        @forelse($movements as $m)
            <tr>
                <td class="whitespace-nowrap">{{ $m->created_at->format('d M Y h:i A') }}</td>
                <td><a href="{{ route('products.show', $m->product_id) }}" class="font-medium hover:underline">{{ $m->product?->display_name }}</a></td>
                <td>@include('inventory._type', ['type' => $m->type, 'label' => $m->type_label])</td>
                <td class="text-xs">
                    @if($m->reference_type === (new \App\Models\Sale)->getMorphClass())
                        <a href="{{ route('sales.show', $m->reference_id) }}" class="text-brand-700 hover:underline">{{ $m->reference_no }}</a>
                    @elseif($m->reference_type === (new \App\Models\Purchase)->getMorphClass())
                        <a href="{{ route('purchases.show', $m->reference_id) }}" class="text-brand-700 hover:underline">{{ $m->reference_no }}</a>
                    @else
                        {{ $m->reference_no }}
                    @endif
                </td>
                <td class="font-mono text-xs">{{ $m->batch?->batch_no }}</td>
                <td class="num font-semibold {{ $m->quantity > 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                <td class="num">{{ $m->balance_after }}</td>
                <td>{{ $m->user?->name }}</td>
                <td class="text-xs text-slate-500">{{ $m->note }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-slate-400 py-10">No movements found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $movements->links() }}</div>
@endsection
