@extends('layouts.app')
@section('title', 'Point of Sale')

@section('content')
<div x-data="pos(@js([
        'taxRate' => (float) setting('tax_rate'),
        'currency' => setting('currency_symbol'),
        'maxDiscount' => (float) setting('cashier_max_discount'),
        'isManager' => auth()->user()->isManagerOrAdmin(),
        'autoPrint' => setting('auto_print_receipt') === '1',
        'routes' => ['lookup' => route('pos.lookup'), 'search' => route('pos.search'), 'store' => route('pos.store')],
    ]))" class="grid xl:grid-cols-[1fr_380px] gap-4">

    {{-- Left: scan + cart --}}
    <div class="space-y-4 min-w-0">
        <div class="card p-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400"><x-icon name="scan" /></div>
                <input x-ref="scan" x-model="query" @input="onQueryInput" @keydown="onScanKeydown"
                       class="input !pl-11 !py-3 !text-base" placeholder="Scan barcode / QR code, or type a medicine name…  (F2)" autocomplete="off" spellcheck="false">
                <div x-show="searching" x-cloak class="absolute right-3 inset-y-0 flex items-center text-xs text-slate-400">searching…</div>

                {{-- Search results --}}
                <div x-show="results.length" x-cloak class="absolute z-30 mt-1 w-full card max-h-96 overflow-y-auto shadow-xl">
                    <template x-for="(p, i) in results" :key="p.id">
                        <button type="button" @click="pick(p)" @mouseenter="highlighted = i"
                                class="w-full text-left px-4 py-2.5 flex items-center justify-between gap-3 border-b border-slate-100"
                                :class="i === highlighted ? 'bg-brand-50' : ''">
                            <div class="min-w-0">
                                <div class="font-medium text-slate-800 truncate">
                                    <span x-text="p.name"></span>
                                    <span x-show="p.rx" class="badge badge-purple ml-1">Rx</span>
                                </div>
                                <div class="text-xs text-slate-500 truncate">
                                    <span x-text="p.generic_name || ''"></span>
                                    <span x-show="p.rack"> · Rack <span x-text="p.rack"></span></span>
                                    <span x-show="p.next_expiry"> · Exp <span x-text="p.next_expiry"></span></span>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="font-semibold tabular-nums" x-text="money(p.price)"></div>
                                <div class="text-xs" :class="p.stock > 0 ? 'text-slate-500' : 'text-red-600 font-medium'" x-text="p.stock > 0 ? p.stock + ' in stock' : 'Out of stock'"></div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
            <div x-show="error" x-cloak x-transition class="mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-700 font-medium" x-text="error"></div>
            <div x-show="toast" x-cloak x-transition class="mt-3 rounded-lg bg-violet-50 border border-violet-200 px-4 py-2 text-sm text-violet-800" x-text="toast"></div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <span class="card-title">Current bill <span class="text-slate-400 font-normal" x-text="`(${cart.length} lines, ${itemCount} items)`"></span></span>
                <button type="button" class="btn btn-secondary btn-sm" @click="clearCart" :disabled="!cart.length">Clear (F8)</button>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Medicine</th><th class="num">Price</th><th class="text-center">Qty</th><th class="num">Amount</th><th></th></tr></thead>
                    <tbody>
                    <template x-for="(line, index) in cart" :key="line.product_id">
                        <tr>
                            <td>
                                <div class="font-medium text-slate-800"><span x-text="line.name"></span> <span x-show="line.rx" class="badge badge-purple">Rx</span></div>
                                <div class="text-xs text-slate-500">
                                    <span x-text="line.generic || ''"></span>
                                    <span x-show="line.expiry"> · Exp <span x-text="line.expiry"></span></span>
                                    · <span x-text="line.stock"></span> in stock
                                </div>
                            </td>
                            <td class="num" x-text="money(line.price)"></td>
                            <td>
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" class="btn btn-secondary btn-sm !px-2" @click="dec(line)">−</button>
                                    <input type="number" min="1" :max="line.stock" class="input !w-16 text-center !py-1" :value="line.quantity"
                                           @change="setQty(line, $event.target.value); $event.target.value = line.quantity" @keydown.enter.prevent="$event.target.blur(); focusScan()">
                                    <button type="button" class="btn btn-secondary btn-sm !px-2" @click="inc(line)">+</button>
                                </div>
                            </td>
                            <td class="num font-semibold" x-text="money(line.price * line.quantity)"></td>
                            <td class="text-right"><button type="button" class="text-slate-400 hover:text-red-600" @click="remove(index)" title="Remove"><x-icon name="trash" class="w-4 h-4" /></button></td>
                        </tr>
                    </template>
                    <tr x-show="!cart.length">
                        <td colspan="5" class="text-center py-16 text-slate-400">
                            <x-icon name="scan" class="w-10 h-10 mx-auto mb-2 text-slate-300" />
                            Scan a product barcode or QR code to start a bill.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-slate-500">Shortcuts: <b>F2</b> scan · <b>F4</b> amount received · <b>F8</b> clear bill · <b>F9</b> or <b>Ctrl+Enter</b> complete sale · <b>Esc</b> close</p>
    </div>

    {{-- Right: payment --}}
    <div class="space-y-4">
        <div class="card card-body space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <input x-model="customer.name" class="input" placeholder="Customer name" maxlength="120">
                <input x-model="customer.phone" class="input" placeholder="Phone" maxlength="30">
            </div>
            <input x-model="customer.doctor" class="input" placeholder="Doctor / prescription ref. (optional)" maxlength="120">
        </div>

        <div class="card card-body space-y-3">
            <div class="flex justify-between text-sm"><span class="text-slate-500">Subtotal</span><span class="tabular-nums" x-text="money(subtotal)"></span></div>
            <div class="flex items-center justify-between gap-2 text-sm">
                <span class="text-slate-500">Discount</span>
                <div class="flex gap-1">
                    <input type="number" min="0" step="0.01" x-model="discountValue" class="input !w-24 !py-1 text-right" @focus="$event.target.select()">
                    <select x-model="discountType" class="input !w-16 !py-1 !px-2"><option value="amount">{{ setting('currency_symbol') }}</option><option value="percent">%</option></select>
                </div>
            </div>
            <div x-show="discount > 0" class="flex justify-between text-sm text-emerald-700"><span>Discount applied</span><span class="tabular-nums" x-text="'− ' + money(discount)"></span></div>
            <p x-show="discountTooHigh" x-cloak class="text-xs text-red-600">Cashiers can give at most {{ setting('cashier_max_discount') }}% discount.</p>
            @if((float) setting('tax_rate') > 0)
                <div class="flex justify-between text-sm"><span class="text-slate-500">Tax ({{ setting('tax_rate') }}%)</span><span class="tabular-nums" x-text="money(tax)"></span></div>
            @endif
            <div class="flex justify-between items-baseline border-t border-slate-200 pt-3">
                <span class="font-semibold">Total</span>
                <span class="text-3xl font-bold text-slate-900 tabular-nums" x-text="money(total)"></span>
            </div>
        </div>

        <div class="card card-body space-y-3">
            <div class="grid grid-cols-3 gap-2">
                @foreach($paymentMethods as $key => $label)
                    <button type="button" class="btn btn-sm !py-2" :class="paymentMethod === '{{ $key }}' ? 'btn-dark' : 'btn-secondary'" @click="paymentMethod = '{{ $key }}'">{{ \Illuminate\Support\Str::before($label, ' /') }}</button>
                @endforeach
            </div>
            <div x-show="paymentMethod === 'cash'" class="space-y-2">
                <label class="label">Amount received (F4)</label>
                <input x-ref="paid" type="number" min="0" step="0.01" x-model="paid" class="input !text-lg text-right" placeholder="Exact amount" @keydown.enter.prevent="checkout()">
                <div class="flex flex-wrap gap-1">
                    <button type="button" class="btn btn-secondary btn-sm" @click="quickCash('exact')">Exact</button>
                    @foreach([100, 500, 1000, 5000] as $note)
                        <button type="button" class="btn btn-secondary btn-sm" @click="quickCash({{ $note }})">{{ number_format($note) }}</button>
                    @endforeach
                </div>
                <div class="flex justify-between items-baseline rounded-lg bg-emerald-50 px-3 py-2">
                    <span class="text-sm text-emerald-800">Change to return</span>
                    <span class="text-xl font-bold text-emerald-800 tabular-nums" x-text="money(change)"></span>
                </div>
                <p x-show="paid !== '' && paidAmount < total" x-cloak class="text-xs text-red-600">Amount received is less than the total.</p>
            </div>
            <input x-model="notes" class="input" placeholder="Note (optional)" maxlength="500">
            <button type="button" class="btn btn-primary btn-lg w-full" @click="checkout()" :disabled="!canCheckout">
                <span x-show="!busy">Complete sale & print (F9)</span>
                <span x-show="busy" x-cloak>Saving…</span>
            </button>
        </div>
    </div>

    {{-- Success panel --}}
    <div x-show="lastSale" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="lastSale = null; focusScan()">
        <div class="card card-body w-full max-w-sm text-center space-y-3">
            <div class="mx-auto w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl">✓</div>
            <div class="text-lg font-semibold">Sale completed</div>
            <div class="text-sm text-slate-500" x-text="lastSale?.invoice_no"></div>
            <div class="text-3xl font-bold" x-text="money(lastSale?.total)"></div>
            <div x-show="lastSale?.change > 0" class="rounded-lg bg-emerald-50 py-2 text-emerald-800">Return change: <b x-text="money(lastSale?.change)"></b></div>
            <div class="grid grid-cols-2 gap-2 pt-2">
                <button type="button" class="btn btn-secondary" @click="printReceipt(lastSale.receipt_url)"><x-icon name="printer" class="w-4 h-4" /> Print again</button>
                <button type="button" class="btn btn-primary" @click="lastSale = null; focusScan()" x-init="$watch('lastSale', v => v && $nextTick(() => $el.focus()))">Next sale</button>
            </div>
        </div>
    </div>

    {{-- Off-screen (not display:none) so browsers will print it --}}
    <iframe x-ref="printFrame" title="receipt" style="position:absolute;left:-10000px;top:0;width:400px;height:600px;border:0"></iframe>
</div>
@endsection
