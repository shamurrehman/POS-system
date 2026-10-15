@extends('layouts.app')
@section('title', 'Suppliers')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div><h2 class="page-title">Suppliers & Distributors</h2></div>
    <div class="flex gap-2">
        <form><input name="q" value="{{ $q }}" class="input" placeholder="Search name or phone"></form>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> Add supplier</a>
    </div>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th class="num">Purchases</th><th class="num">Total bought</th><th class="num">Amount due</th><th></th></tr></thead>
        <tbody>
        @forelse($suppliers as $s)
            @php $due = (float) $s->purchases_sum_total - (float) $s->purchases_sum_paid_amount; @endphp
            <tr class="{{ $s->is_active ? '' : 'opacity-50' }}">
                <td class="font-medium">{{ $s->name }} @unless($s->is_active)<span class="badge badge-slate">Inactive</span>@endunless</td>
                <td>{{ $s->contact_person }}</td>
                <td>{{ $s->phone }}</td>
                <td class="num">{{ $s->purchases_count }}</td>
                <td class="num">{{ money($s->purchases_sum_total) }}</td>
                <td class="num {{ $due > 0 ? 'text-red-600 font-semibold' : '' }}">{{ money($due) }}</td>
                <td class="text-right whitespace-nowrap">
                    <a href="{{ route('purchases.index', ['supplier_id' => $s->id]) }}" class="btn btn-secondary btn-sm">Purchases</a>
                    <a href="{{ route('suppliers.edit', $s) }}" class="btn btn-secondary btn-sm">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-slate-400 py-8">No suppliers yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $suppliers->links() }}</div>
@endsection
