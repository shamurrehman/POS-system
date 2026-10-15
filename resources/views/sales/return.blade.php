@extends('layouts.app')
@section('title', 'Return items · '.$sale->invoice_no)

@section('content')
<div class="mb-4">
    <h2 class="page-title">Customer return</h2>
    <p class="page-subtitle">Invoice {{ $sale->invoice_no }} · {{ $sale->created_at->format('d M Y h:i A') }} · Total {{ money($sale->total) }}</p>
</div>

<form method="POST" action="{{ route('sales.return.store', $sale) }}" class="space-y-4" data-confirm="Save this return and refund the customer?">
    @csrf
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Medicine</th><th>Batch</th><th class="num">Sold</th><th class="num">Already returned</th><th class="num">Price</th><th class="text-center">Return qty</th></tr></thead>
            <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td class="font-medium">{{ $item->product_name }}</td>
                    <td class="text-xs">{{ $item->batch_no }} @if($item->expiry_date)· {{ $item->expiry_date->format('M Y') }}@endif</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $item->returned_quantity }}</td>
                    <td class="num">{{ money($item->unit_price) }}</td>
                    <td class="text-center">
                        @if($item->returnable_quantity > 0)
                            <input type="number" name="quantities[{{ $item->id }}]" min="0" max="{{ $item->returnable_quantity }}" value="{{ old('quantities.'.$item->id, 0) }}" class="input !w-24 mx-auto text-center">
                        @else
                            <span class="text-slate-400 text-xs">fully returned</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card card-body grid md:grid-cols-2 gap-4">
        <div>
            <label class="label">Reason</label>
            <input name="reason" value="{{ old('reason') }}" class="input" maxlength="250" placeholder="e.g. Wrong medicine given, doctor changed prescription">
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="restock" value="0">
                <input type="checkbox" name="restock" value="1" class="checkbox" checked>
                Put returned items back into stock (untick if damaged / opened)
            </label>
        </div>
    </div>
    <p class="text-sm text-slate-500">The refund is calculated from what the customer paid, so any bill discount and tax are shared across the returned items.</p>
    <div class="flex gap-2 justify-end">
        <a href="{{ route('sales.show', $sale) }}" class="btn btn-secondary">Cancel</a>
        <button class="btn btn-warning">Save return</button>
    </div>
</form>
@endsection
