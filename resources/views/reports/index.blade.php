@extends('layouts.app')
@section('title', 'Business Overview')

@section('content')
@include('reports._toolbar', [
    'title' => 'Business overview',
    'subtitle' => $from->format('d M Y').' to '.$to->format('d M Y'),
    'csv' => false,
    'filters' => '<div><label class="label">From</label><input type="date" name="from" value="'.e($from->toDateString()).'" class="input"></div>
                  <div><label class="label">To</label><input type="date" name="to" value="'.e($to->toDateString()).'" class="input"></div>',
])
@include('partials.report-header', ['title' => 'Business Overview', 'subtitle' => $from->format('d M Y').' – '.$to->format('d M Y')])

@include('reports._summary')

<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <div class="card">
        <div class="card-header"><span class="card-title">Stock on hand (now)</span></div>
        <div class="card-body space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Value at cost</span><b>{{ money($inventory['cost']) }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Value at selling price</span><b>{{ money($inventory['retail']) }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Potential profit</span><b class="text-emerald-700">{{ money($inventory['potential_profit']) }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Units</span><b>{{ number_format($inventory['units']) }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Products in stock</span><b>{{ $inventory['in_stock_products'] }} / {{ $inventory['products'] }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Expired stock (cost)</span><b class="{{ $inventory['expired_value'] > 0 ? 'text-red-600' : '' }}">{{ money($inventory['expired_value']) }}</b></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Purchases in period</span></div>
        <div class="card-body space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Stock-ins</span><b>{{ $purchases['count'] }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Total purchased</span><b>{{ money($purchases['total']) }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Paid to suppliers</span><b>{{ money($purchases['paid']) }}</b></div>
            <div class="flex justify-between"><span class="text-slate-500">Still owed</span><b class="{{ $purchases['due'] > 0 ? 'text-red-600' : '' }}">{{ money($purchases['due']) }}</b></div>
        </div>
        <div class="card-header border-t"><span class="card-title">By payment method</span></div>
        <div class="card-body space-y-2 text-sm">
            @forelse($payments as $p)
                <div class="flex justify-between"><span class="text-slate-500">{{ \App\Models\Sale::PAYMENT_METHODS[$p->payment_method] ?? $p->payment_method }} ({{ $p->invoices }})</span><b>{{ money($p->total) }}</b></div>
            @empty
                <div class="text-slate-400">No sales.</div>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Sales by category</span></div>
        <table class="table">
            <tbody>
            @forelse($categories as $c)
                <tr><td>{{ $c->category }}</td><td class="num">{{ (int) $c->qty }}</td><td class="num font-medium">{{ money($c->revenue) }}</td></tr>
            @empty
                <tr><td class="text-center text-slate-400 py-6">No sales.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="no-print grid md:grid-cols-4 gap-3">
    @foreach([
        ['reports.daily', 'Daily report', 'Every invoice of a day, by cashier and payment method'],
        ['reports.monthly', 'Monthly report', 'Day-by-day sales and profit for a month'],
        ['reports.yearly', 'Yearly report', 'Month-by-month for a year'],
        ['reports.products', 'Product sales', 'Best and worst sellers with profit'],
        ['reports.inventory', 'Stock valuation', 'Every product with cost and retail value'],
        ['reports.expiry', 'Expiry report', 'Batches expiring within N days'],
        ['reports.purchases', 'Purchase report', 'Stock-ins and supplier dues'],
        ['inventory.history', 'Stock history', 'Every stock movement'],
    ] as [$route, $label, $desc])
        <a href="{{ route($route) }}" class="card card-body hover:ring-2 hover:ring-brand-200 transition">
            <div class="font-semibold text-slate-800">{{ $label }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $desc }}</div>
        </a>
    @endforeach
</div>
@endsection
