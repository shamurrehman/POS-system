@extends('layouts.app')
@section('title', 'Product Sales')

@section('content')
@include('reports._toolbar', [
    'title' => 'Product-wise sales',
    'subtitle' => $from->format('d M Y').' to '.$to->format('d M Y'),
    'filters' => '<div><label class="label">From</label><input type="date" name="from" value="'.e($from->toDateString()).'" class="input"></div>
                  <div><label class="label">To</label><input type="date" name="to" value="'.e($to->toDateString()).'" class="input"></div>',
])
@include('partials.report-header', ['title' => 'Product Sales', 'subtitle' => $from->format('d M Y').' – '.$to->format('d M Y')])

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>#</th><th>Medicine</th><th class="num">Qty sold</th><th class="num">Revenue</th><th class="num">Gross profit</th><th class="num">Margin</th></tr></thead>
        <tbody>
        @forelse($rows as $i => $r)
            <tr>
                <td class="text-slate-400">{{ $i + 1 }}</td>
                <td><a href="{{ route('products.show', $r->product_id) }}" class="font-medium hover:underline">{{ $r->product_name }}</a></td>
                <td class="num">{{ number_format($r->qty) }}</td>
                <td class="num font-medium">{{ money($r->revenue) }}</td>
                <td class="num text-emerald-700">{{ money($r->profit) }}</td>
                <td class="num">{{ $r->revenue > 0 ? round($r->profit / $r->revenue * 100, 1).'%' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-slate-400 py-10">No sales in this period.</td></tr>
        @endforelse
        </tbody>
        @if($rows->isNotEmpty())
            <tfoot><tr><td colspan="2">Total</td><td class="num">{{ number_format($rows->sum('qty')) }}</td><td class="num">{{ money($rows->sum('revenue')) }}</td><td class="num">{{ money($rows->sum('profit')) }}</td><td></td></tr></tfoot>
        @endif
    </table>
</div>
<p class="text-xs text-slate-500 mt-2">Revenue is before bill-level discounts and tax, net of returned units.</p>
@endsection
