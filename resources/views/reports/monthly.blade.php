@extends('layouts.app')
@section('title', 'Monthly Report')

@section('content')
@include('reports._toolbar', [
    'title' => 'Monthly sales history',
    'subtitle' => $month->format('F Y'),
    'filters' => '<div><label class="label">Month</label><input type="month" name="month" value="'.e($month->format('Y-m')).'" max="'.today()->format('Y-m').'" class="input"></div>',
])
@include('partials.report-header', ['title' => 'Monthly Sales History', 'subtitle' => $month->format('F Y')])

@include('reports._summary')

<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <div class="card lg:col-span-2 no-print">
        <div class="card-header"><span class="card-title">Daily trend</span></div>
        <div class="card-body"><div class="h-64"><canvas id="monthChart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Month at a glance</span></div>
        <div class="card-body text-sm space-y-2">
            @foreach($payments as $p)
                <div class="flex justify-between"><span class="text-slate-500">{{ \App\Models\Sale::PAYMENT_METHODS[$p->payment_method] ?? $p->payment_method }}</span><b>{{ money($p->total) }}</b></div>
            @endforeach
            <div class="flex justify-between border-t pt-2"><span class="text-slate-500">Purchases ({{ $purchases['count'] }})</span><b>{{ money($purchases['total']) }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Owed to suppliers</span><b>{{ money($purchases['due']) }}</b></div>
            @php $best = $days->sortByDesc('net')->first(); @endphp
            @if($best && $best['net'] > 0)<div class="flex justify-between border-t pt-2"><span class="text-slate-500">Best day</span><b>{{ $best['date']->format('d M') }} · {{ money($best['net']) }}</b></div>@endif
        </div>
    </div>
</div>

<div class="card overflow-x-auto mb-4">
    <table class="table">
        <thead><tr><th>Date</th><th class="num">Invoices</th><th class="num">Discount</th><th class="num">Sales</th><th class="num">Returns</th><th class="num">Net sales</th><th class="num">Cost</th><th class="num">Profit</th></tr></thead>
        <tbody>
        @foreach($days as $d)
            <tr class="{{ $d['invoices'] === 0 ? 'text-slate-400' : '' }}">
                <td><a href="{{ route('reports.daily', ['date' => $d['date']->toDateString()]) }}" class="hover:underline">{{ $d['date']->format('D, d M') }}</a></td>
                <td class="num">{{ $d['invoices'] }}</td>
                <td class="num">{{ money($d['discount']) }}</td>
                <td class="num">{{ money($d['sales']) }}</td>
                <td class="num">{{ money($d['returns']) }}</td>
                <td class="num font-medium">{{ money($d['net']) }}</td>
                <td class="num">{{ money($d['cost']) }}</td>
                <td class="num text-emerald-700">{{ money($d['profit']) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr>
            <td>Total</td><td class="num">{{ $days->sum('invoices') }}</td><td class="num">{{ money($days->sum('discount')) }}</td><td class="num">{{ money($days->sum('sales')) }}</td>
            <td class="num">{{ money($days->sum('returns')) }}</td><td class="num">{{ money($days->sum('net')) }}</td><td class="num">{{ money($days->sum('cost')) }}</td><td class="num">{{ money($days->sum('profit')) }}</td>
        </tr></tfoot>
    </table>
</div>

<div class="grid md:grid-cols-2 gap-4">
    <div class="card overflow-x-auto">
        <div class="card-header"><span class="card-title">Top 15 medicines</span></div>
        <table class="table">
            <thead><tr><th>Medicine</th><th class="num">Qty</th><th class="num">Revenue</th><th class="num">Profit</th></tr></thead>
            <tbody>
            @forelse($topProducts as $p)
                <tr><td>{{ $p->product_name }}</td><td class="num">{{ (int) $p->qty }}</td><td class="num">{{ money($p->revenue) }}</td><td class="num">{{ money($p->profit) }}</td></tr>
            @empty <tr><td colspan="4" class="text-center text-slate-400 py-6">No sales.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="card overflow-x-auto">
        <div class="card-header"><span class="card-title">By category</span></div>
        <table class="table">
            <thead><tr><th>Category</th><th class="num">Qty</th><th class="num">Revenue</th></tr></thead>
            <tbody>
            @forelse($categories as $c)
                <tr><td>{{ $c->category }}</td><td class="num">{{ (int) $c->qty }}</td><td class="num">{{ money($c->revenue) }}</td></tr>
            @empty <tr><td colspan="3" class="text-center text-slate-400 py-6">No sales.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
@php $chartRows = $days->map(fn ($d) => ['l' => $d['date']->format('d'), 's' => round($d['net'], 2), 'p' => round($d['profit'], 2)])->values(); @endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = @json($chartRows);
    new Chart(document.getElementById('monthChart'), {
        type: 'bar',
        data: { labels: rows.map(r => r.l), datasets: [
            { label: 'Net sales', data: rows.map(r => r.s), backgroundColor: '#10b981', borderRadius: 3 },
            { label: 'Profit', data: rows.map(r => r.p), type: 'line', borderColor: '#0f172a', tension: .3, pointRadius: 2 },
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } },
    });
});
</script>
@endpush
