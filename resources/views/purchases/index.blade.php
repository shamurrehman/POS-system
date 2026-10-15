@extends('layouts.app')
@section('title', 'Stock In / Purchases')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">Stock In / Purchases</h2>
        <p class="page-subtitle">Total {{ money($totals->total) }} · Paid {{ money($totals->paid) }} · <span class="{{ $totals->total - $totals->paid > 0 ? 'text-red-600' : '' }}">Due {{ money($totals->total - $totals->paid) }}</span></p>
    </div>
    <a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> New stock in</a>
</div>

<form class="card card-body mb-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
    <div><label class="label">Search</label><input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Ref / invoice no"></div>
    <div><label class="label">Supplier</label>
        <select name="supplier_id" class="input"><option value="">All</option>
            @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(($filters['supplier_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </div>
    <div><label class="label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
    <div><label class="label">Payment</label>
        <select name="payment_status" class="input"><option value="">All</option>
            @foreach(['paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid'] as $k => $v)<option value="{{ $k }}" @selected(($filters['payment_status'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div class="flex gap-2"><button class="btn btn-dark flex-1">Filter</button><a href="{{ route('purchases.index') }}" class="btn btn-secondary">Reset</a></div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Reference</th><th>Date</th><th>Supplier</th><th>Supplier invoice</th><th class="num">Lines</th><th class="num">Total</th><th class="num">Due</th><th>Status</th><th>By</th></tr></thead>
        <tbody>
        @forelse($purchases as $p)
            <tr>
                <td><a href="{{ route('purchases.show', $p) }}" class="font-medium text-brand-700 hover:underline">{{ $p->reference_no }}</a></td>
                <td>{{ $p->purchase_date->format('d M Y') }}</td>
                <td>{{ $p->supplier?->name ?? '—' }}</td>
                <td>{{ $p->supplier_invoice_no }}</td>
                <td class="num">{{ $p->items_count }}</td>
                <td class="num font-medium">{{ money($p->total) }}</td>
                <td class="num {{ $p->due_amount > 0 ? 'text-red-600' : '' }}">{{ money($p->due_amount) }}</td>
                <td><span class="badge {{ ['paid' => 'badge-green', 'partial' => 'badge-amber', 'unpaid' => 'badge-red'][$p->payment_status] }}">{{ ucfirst($p->payment_status) }}</span></td>
                <td>{{ $p->user?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-slate-400 py-10">No purchases recorded.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $purchases->links() }}</div>
@endsection
