@extends('layouts.app')
@section('title', 'Yearly Report')

@section('content')
@include('reports._toolbar', [
    'title' => 'Yearly report',
    'subtitle' => (string) $year,
    'filters' => '<div><label class="label">Year</label><input type="number" name="year" min="2000" max="2100" value="'.$year.'" class="input !w-28"></div>',
])
@include('partials.report-header', ['title' => 'Yearly Sales Report', 'subtitle' => (string) $year])

@include('reports._summary')

<div class="card mb-4 no-print"><div class="card-body"><div class="h-72"><canvas id="yearChart"></canvas></div></div></div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Month</th><th class="num">Invoices</th><th class="num">Sales</th><th class="num">Returns</th><th class="num">Net sales</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th></tr></thead>
        <tbody>
        @foreach($months as $m)
            @php $rev = $m['net'] - $m['tax']; @endphp
            <tr class="{{ $m['invoices'] === 0 ? 'text-slate-400' : '' }}">
                <td><a href="{{ route('reports.monthly', ['month' => $m['date']->format('Y-m')]) }}" class="hover:underline">{{ $m['date']->format('F') }}</a></td>
                <td class="num">{{ number_format($m['invoices']) }}</td>
                <td class="num">{{ money($m['sales']) }}</td>
                <td class="num">{{ money($m['returns']) }}</td>
                <td class="num font-medium">{{ money($m['net']) }}</td>
                <td class="num">{{ money($m['cost']) }}</td>
                <td class="num text-emerald-700">{{ money($m['profit']) }}</td>
                <td class="num">{{ $rev > 0 ? round($m['profit'] / $rev * 100, 1).'%' : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr>
            <td>Total</td><td class="num">{{ number_format($months->sum('invoices')) }}</td><td class="num">{{ money($months->sum('sales')) }}</td><td class="num">{{ money($months->sum('returns')) }}</td>
            <td class="num">{{ money($months->sum('net')) }}</td><td class="num">{{ money($months->sum('cost')) }}</td><td class="num">{{ money($months->sum('profit')) }}</td><td></td>
        </tr></tfoot>
    </table>
</div>
@endsection

@push('scripts')
@php $chartRows = $months->map(fn ($m) => ['l' => $m['date']->format('M'), 's' => round($m['net'], 2), 'p' => round($m['profit'], 2)])->values(); @endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = @json($chartRows);
    new Chart(document.getElementById('yearChart'), {
        type: 'bar',
        data: { labels: rows.map(r => r.l), datasets: [
            { label: 'Net sales', data: rows.map(r => r.s), backgroundColor: '#059669', borderRadius: 4 },
            { label: 'Profit', data: rows.map(r => r.p), backgroundColor: '#94a3b8', borderRadius: 4 },
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } },
    });
});
</script>
@endpush
