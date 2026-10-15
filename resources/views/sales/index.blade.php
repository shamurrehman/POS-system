@extends('layouts.app')
@section('title', 'Sales & Invoices')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">Sales & Invoices</h2>
        <p class="page-subtitle">{{ number_format($totals->n) }} invoices · {{ money($totals->total) }} billed · {{ money($totals->returned) }} refunded</p>
    </div>
    <a href="{{ route('pos') }}" class="btn btn-primary"><x-icon name="cart" class="w-4 h-4" /> New sale</a>
</div>

<form class="card card-body mb-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
    <div class="col-span-2"><label class="label">Search</label><input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Invoice no, customer or phone"></div>
    <div><label class="label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
    @if(auth()->user()->isManagerOrAdmin())
        <div><label class="label">Cashier</label>
            <select name="user_id" class="input"><option value="">All</option>
                @foreach($users as $u)<option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
            </select>
        </div>
    @endif
    <div><label class="label">Payment</label>
        <select name="payment_method" class="input"><option value="">All</option>
            @foreach(\App\Models\Sale::PAYMENT_METHODS as $k => $v)<option value="{{ $k }}" @selected(($filters['payment_method'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div class="flex gap-2 md:col-span-6 justify-end">
        <a href="{{ route('sales.index') }}" class="btn btn-secondary">Reset</a>
        <button class="btn btn-dark"><x-icon name="search" class="w-4 h-4" /> Filter</button>
    </div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Cashier</th><th>Payment</th><th class="num">Discount</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($sales as $s)
            <tr>
                <td><a href="{{ route('sales.show', $s) }}" class="font-medium text-brand-700 hover:underline">{{ $s->invoice_no }}</a></td>
                <td class="whitespace-nowrap">{{ $s->created_at->format('d M Y, h:i A') }}</td>
                <td>{{ $s->customer_name ?: 'Walk-in' }}<div class="text-xs text-slate-500">{{ $s->customer_phone }}</div></td>
                <td>{{ $s->user?->name }}</td>
                <td><span class="badge badge-slate">{{ $s->payment_label }}</span></td>
                <td class="num">{{ $s->discount > 0 ? money($s->discount) : '—' }}</td>
                <td class="num font-semibold">{{ money($s->total) }}</td>
                <td>@include('sales._status', ['sale' => $s])</td>
                <td class="text-right whitespace-nowrap">
                    <a href="{{ route('sales.receipt', ['sale' => $s, 'print' => 1]) }}" target="_blank" class="btn btn-secondary btn-sm" title="Reprint receipt"><x-icon name="printer" class="w-4 h-4" /></a>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-slate-400 py-10">No sales found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $sales->links() }}</div>
@endsection
