@extends('layouts.app')
@section('title', 'New stock in')

@section('content')
<div x-data="purchaseForm(@js([
        'today' => today()->toDateString(),
        'currency' => setting('currency_symbol'),
        'routes' => ['lookup' => route('pos.lookup'), 'search' => route('pos.search'), 'store' => route('purchases.store')],
    ]))" class="space-y-4">

    <div>
        <h2 class="page-title">Receive stock from supplier</h2>
        <p class="page-subtitle">Scan each pack (QR codes fill in the batch and expiry automatically) or search by name, then enter quantity and prices. Batch and expiry are optional for items that don't have them.</p>
    </div>

    <div class="card card-body grid md:grid-cols-4 gap-4">
        <div><label class="label">Supplier</label>
            <select x-model="form.supplier_id" class="input"><option value="">— None —</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
            <a href="{{ route('suppliers.create') }}" class="text-xs text-brand-700 hover:underline" target="_blank">+ add supplier</a>
        </div>
        <div><label class="label">Supplier invoice no</label><input x-model="form.supplier_invoice_no" class="input" maxlength="100"></div>
        <div><label class="label">Date received *</label><input type="date" x-model="form.purchase_date" max="{{ today()->toDateString() }}" class="input"></div>
        <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="form.update_prices" class="checkbox"> Update product cost & selling prices</label></div>
    </div>

    <div class="card">
        <div class="p-4 border-b border-slate-200 relative">
            <div class="absolute inset-y-0 left-4 pl-3 flex items-center text-slate-400"><x-icon name="scan" /></div>
            <input x-ref="scan" x-model="query" @input="onInput" @keydown="onKeydown" class="input !pl-11 !py-3" placeholder="Scan barcode / QR or type medicine name, then press Enter" autocomplete="off">
            <div x-show="results.length" x-cloak class="absolute z-30 left-4 right-4 mt-1 card max-h-80 overflow-y-auto shadow-xl">
                <template x-for="(p, i) in results" :key="p.id">
                    <button type="button" @click="pick(p)" @mouseenter="highlighted = i" class="w-full text-left px-4 py-2 flex justify-between border-b border-slate-100" :class="i === highlighted ? 'bg-brand-50' : ''">
                        <span><span class="font-medium" x-text="p.name"></span> <span class="text-xs text-slate-500" x-text="p.generic_name || ''"></span></span>
                        <span class="text-xs text-slate-500" x-text="'Stock ' + p.stock"></span>
                    </button>
                </template>
            </div>
            <p x-show="message" x-cloak class="mt-2 text-sm text-red-600" x-text="message"></p>
        </div>
        <div class="overflow-x-auto" x-ref="lines">
            <table class="table">
                <thead><tr><th>Medicine</th><th>Batch no</th><th>Expiry</th><th>Qty *</th><th>Unit cost *</th><th>Sale price *</th><th class="num">Margin</th><th class="num">Line total</th><th></th></tr></thead>
                <tbody>
                <template x-for="(l, i) in lines" :key="l.key">
                    <tr @keydown.enter.prevent="$refs.scan.focus()">
                        <td class="font-medium min-w-48"><span x-text="l.name"></span><div class="text-xs text-slate-500" x-text="'per ' + l.unit"></div></td>
                        <td><input data-batch x-model="l.batch_no" placeholder="optional" class="input !py-1 !w-32" :class="err(`items.${i}.batch_no`) && 'border-red-400'" maxlength="100"></td>
                        <td><input type="date" x-model="l.expiry_date" class="input !py-1 !w-40" :class="err(`items.${i}.expiry_date`) && 'border-red-400'"></td>
                        <td><input type="number" min="1" x-model.number="l.quantity" class="input !py-1 !w-24" :class="err(`items.${i}.quantity`) && 'border-red-400'"></td>
                        <td><input type="number" min="0" step="0.01" x-model.number="l.unit_cost" class="input !py-1 !w-28"></td>
                        <td><input type="number" min="0" step="0.01" x-model.number="l.sale_price" class="input !py-1 !w-28"></td>
                        <td class="num text-xs" :class="margin(l) < 0 ? 'text-red-600' : 'text-slate-500'" x-text="margin(l) + '%'"></td>
                        <td class="num font-medium" x-text="money(lineTotal(l))"></td>
                        <td><button type="button" class="text-slate-400 hover:text-red-600" @click="remove(i)"><x-icon name="trash" class="w-4 h-4" /></button></td>
                    </tr>
                </template>
                <tr x-show="!lines.length"><td colspan="9" class="text-center text-slate-400 py-10">No medicines added yet.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="card card-body lg:col-span-2"><label class="label">Notes</label><textarea x-model="form.notes" rows="4" class="input" maxlength="1000"></textarea></div>
        <div class="card card-body space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Lines / units</span><span x-text="lines.length + ' / ' + units"></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span x-text="money(subtotal)"></span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500">Discount</span><input type="number" min="0" step="0.01" x-model="form.discount" class="input !w-32 !py-1 text-right"></div>
            <div class="flex justify-between items-center"><span class="text-slate-500">Tax / charges</span><input type="number" min="0" step="0.01" x-model="form.tax" class="input !w-32 !py-1 text-right"></div>
            <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Total</span><span x-text="money(total)"></span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500">Paid now</span><input type="number" min="0" step="0.01" x-model="form.paid_amount" class="input !w-32 !py-1 text-right" placeholder="Full"></div>
            <button type="button" class="btn btn-primary w-full btn-lg" @click="save()" :disabled="busy || !lines.length">
                <span x-show="!busy">Save stock in</span><span x-show="busy" x-cloak>Saving…</span>
            </button>
        </div>
    </div>
</div>
@endsection
