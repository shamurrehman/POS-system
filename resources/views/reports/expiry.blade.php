@extends('layouts.app')
@section('title', 'Expiry Report')

@section('content')
@include('reports._toolbar', [
    'title' => 'Expiry report',
    'subtitle' => 'Expired stock and batches expiring within '.$days.' days',
    'filters' => '<div><label class="label">Within days</label><input type="number" name="days" min="1" max="730" value="'.$days.'" class="input !w-28"></div>',
])
@include('partials.report-header', ['title' => 'Expiry Report', 'subtitle' => 'Within '.$days.' days'])

@php $expiredTotal = $batches->filter->isExpired()->sum(fn ($b) => $b->quantity * $b->purchase_price); @endphp
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
    <div class="stat"><div class="stat-label">Batches listed</div><div class="stat-value">{{ $batches->count() }}</div></div>
    <div class="stat"><div class="stat-label">Already expired (cost)</div><div class="stat-value text-red-600">{{ money($expiredTotal) }}</div></div>
    <div class="stat"><div class="stat-label">Expiring soon (cost)</div><div class="stat-value text-amber-600">{{ money($batches->sum(fn ($b) => $b->quantity * $b->purchase_price) - $expiredTotal) }}</div></div>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th>Status</th><th class="num">Qty</th><th class="num">Cost value</th><th>Supplier</th><th>Rack</th></tr></thead>
        <tbody>
        @forelse($batches as $b)
            @php $d = $b->daysToExpiry(); @endphp
            <tr>
                <td class="font-medium">{{ $b->product?->display_name }}</td>
                <td class="font-mono text-xs">{{ $b->batch_no }}</td>
                <td>{{ $b->expiry_date->format('d M Y') }}</td>
                <td>@if($d < 0)<span class="badge badge-red">Expired</span>@elseif($d <= 30)<span class="badge badge-red">{{ $d }} days</span>@else<span class="badge badge-amber">{{ $d }} days</span>@endif</td>
                <td class="num">{{ $b->quantity }}</td>
                <td class="num">{{ money($b->quantity * $b->purchase_price) }}</td>
                <td>{{ $b->supplier?->name ?? '—' }}</td>
                <td>{{ $b->product?->rack_location }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-slate-400 py-10">No stock expiring in this window.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
