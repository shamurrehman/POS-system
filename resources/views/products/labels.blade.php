@extends('layouts.app')
@section('title', 'Barcode labels')

@section('content')
<div class="no-print mb-4">
    <h2 class="page-title">Print barcode labels</h2>
    <p class="page-subtitle">For shelf tags, or for medicines that came without a scannable barcode.</p>
</div>

<form class="no-print card card-body mb-4 grid md:grid-cols-4 gap-3 items-end" x-data="{ q: '', results: [], pick(p) { this.$refs.pid.value = p.id; this.$el.submit(); } }">
    <div class="md:col-span-2 relative">
        <label class="label">Product</label>
        <input x-model="q" @input.debounce.250ms="q.length > 1 ? fetch('{{ route('pos.search') }}?q=' + encodeURIComponent(q)).then(r => r.json()).then(d => results = d) : results = []"
               class="input" placeholder="Type to search…" value="{{ $product?->display_name }}">
        <input type="hidden" name="product_id" x-ref="pid" value="{{ $product?->id }}">
        <div x-show="results.length" x-cloak class="absolute z-20 mt-1 w-full card max-h-72 overflow-auto">
            <template x-for="p in results" :key="p.id"><button type="button" class="block w-full text-left px-3 py-2 hover:bg-slate-50 text-sm" @click="pick(p)" x-text="p.name + ' · ' + (p.barcode || 'no barcode')"></button></template>
        </div>
    </div>
    <div><label class="label">Number of labels</label><input type="number" name="copies" min="1" max="500" value="{{ $copies }}" class="input"></div>
    <div class="flex gap-2"><button class="btn btn-dark flex-1">Update</button>@if($product)<button type="button" onclick="window.print()" class="btn btn-primary"><x-icon name="printer" class="w-4 h-4" /> Print</button>@endif</div>
</form>

@if($product)
    <div class="grid grid-cols-3 md:grid-cols-4 gap-2 print:gap-1">
        @for($i = 0; $i < $copies; $i++)
            <div class="border border-dashed border-slate-300 bg-white p-2 text-center" style="break-inside: avoid">
                <div class="text-[11px] font-semibold leading-tight truncate">{{ $product->display_name }}</div>
                <svg class="barcode mx-auto" data-code="{{ $product->barcode }}"></svg>
                <div class="text-[11px] font-bold">{{ money($product->sale_price) }}</div>
            </div>
        @endfor
    </div>
@else
    <div class="card card-body text-center text-slate-400 py-16">Choose a product to generate labels.</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('svg.barcode').forEach((el) => {
        const code = el.dataset.code;
        if (!code) return;
        // Encode the stored code exactly. EAN-13 is used only when its check digit is valid
        // (JsBarcode throws otherwise); recomputing it would print a code the POS can't find.
        try {
            if (!/^\d{13}$/.test(code)) throw new Error('not EAN-13');
            JsBarcode(el, code, { format: 'EAN13', height: 40, width: 1.4, fontSize: 11, margin: 2 });
        } catch (e) {
            JsBarcode(el, code, { format: 'CODE128', height: 40, width: 1.4, fontSize: 11, margin: 2 });
        }
    });
});
</script>
@endpush
