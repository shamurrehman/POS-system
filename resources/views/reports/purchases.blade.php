@extends('layouts.app')
@section('title', 'Purchase Report')

@section('content')
@include('reports._toolbar', [
    'title' => 'Purchase report',
    'subtitle' => $from->format('d M Y').' to '.$to->format('d M Y'),
    'filters' => '<div><label class="label">From</label><input type="date" name="from" value="'.e($from->toDateString()).'" class="input"></div>
                  <div><label class="label">To</label><input type="date" name="to" value="'.e($to->toDateString()).'" class="input"></div>',
])
@include('partials.report-header', ['title' => 'Purchase Report', 'subtitle' => $from->format('d M Y').' – '.$to->format('d M Y')])

<div class="grid lg:grid-cols-3 gap-4">
    <div class="card overflow-x-auto">
        <div class="card-header"><span class="card-title">By supplier</span></div>
        <table class="table">
            <thead><tr><th>Supplier</th><th class="num">Total</th><th class="num">Due</th></tr></thead>
            <tbody>
            @forelse($bySupplier as $name => $s)
                <tr><td>{{ $name }} <span class="text-xs text-slate-400">({{ $s['count'] }})</span></td><td class="num">{{ money($s['total']) }}</td><td class="num {{ $s['due'] > 0 ? 'text-red-600' : '' }}">{{ money($s['due']) }}</td></tr>
            @empty <tr><td colspan="3" class="text-center text-slate-400 py-6">None.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="card lg:col-span-2 overflow-x-auto">
        <table class="table">
            <thead><tr><th>Reference</th><th>Date</th><th>Supplier</th><th class="num">Lines</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Due</th></tr></thead>
            <tbody>
            @forelse($purchases as $p)
                <tr>
                    <td><a href="{{ route('purchases.show', $p) }}" class="text-brand-700 hover:underline">{{ $p->reference_no }}</a><div class="text-xs text-slate-500">{{ $p->supplier_invoice_no }}</div></td>
                    <td>{{ $p->purchase_date->format('d M Y') }}</td>
                    <td>{{ $p->supplier?->name ?? '—' }}</td>
                    <td class="num">{{ $p->items_count }}</td>
                    <td class="num font-medium">{{ money($p->total) }}</td>
                    <td class="num">{{ money($p->paid_amount) }}</td>
                    <td class="num {{ $p->due_amount > 0 ? 'text-red-600' : '' }}">{{ money($p->due_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400 py-10">No purchases in this period.</td></tr>
            @endforelse
            </tbody>
            @if($purchases->isNotEmpty())
                <tfoot><tr><td colspan="4">Total</td><td class="num">{{ money($purchases->sum('total')) }}</td><td class="num">{{ money($purchases->sum('paid_amount')) }}</td><td class="num">{{ money($purchases->sum(fn ($p) => $p->due_amount)) }}</td></tr></tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
