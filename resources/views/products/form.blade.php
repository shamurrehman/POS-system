@extends('layouts.app')
@section('title', $product->exists ? 'Edit '.$product->name : 'Add medicine')

@section('content')
<div class="mb-4">
    <h2 class="page-title">{{ $product->exists ? 'Edit '.$product->name : 'Add a new medicine' }}</h2>
    <p class="page-subtitle">Scan the pack barcode into the barcode field. Leave it empty to generate an in-store barcode you can print.</p>
</div>

<form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="space-y-4 max-w-5xl">
    @csrf
    @if($product->exists) @method('PUT') @endif

    <div class="card card-body grid md:grid-cols-3 gap-4">
        <div class="md:col-span-2"><label class="label">Brand / product name *</label><input name="name" value="{{ old('name', $product->name) }}" class="input" required maxlength="150" autofocus></div>
        <div><label class="label">Barcode / GTIN</label><input name="barcode" value="{{ old('barcode', $product->barcode) }}" class="input font-mono" maxlength="100" placeholder="Scan here" onkeydown="if(event.key==='Enter'){event.preventDefault();}"></div>
        <div class="md:col-span-2"><label class="label">Generic name / formula</label><input name="generic_name" value="{{ old('generic_name', $product->generic_name) }}" class="input" maxlength="150" placeholder="e.g. Paracetamol"></div>
        <div><label class="label">Strength</label><input name="strength" value="{{ old('strength', $product->strength) }}" class="input" maxlength="50" placeholder="e.g. 500mg"></div>
        <div><label class="label">Dosage form</label>
            <select name="dosage_form" class="input"><option value="">—</option>
                @foreach(\App\Models\Product::DOSAGE_FORMS as $f)<option @selected(old('dosage_form', $product->dosage_form) === $f)>{{ $f }}</option>@endforeach
            </select>
        </div>
        <div><label class="label">Category</label>
            <select name="category_id" class="input"><option value="">—</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="label">Manufacturer / company</label><input name="manufacturer" value="{{ old('manufacturer', $product->manufacturer) }}" class="input" maxlength="150"></div>
    </div>

    <div class="card card-body grid md:grid-cols-5 gap-4">
        <div><label class="label">Selling unit *</label><input name="unit" value="{{ old('unit', $product->unit) }}" class="input" required maxlength="30" placeholder="pcs, strip, box, bottle"></div>
        <div><label class="label">Cost price *</label><input type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price ?? 0) }}" class="input" required></div>
        <div><label class="label">Selling price *</label><input type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $product->sale_price ?? 0) }}" class="input" required></div>
        <div><label class="label">Reorder level *</label><input type="number" min="0" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level) }}" class="input" required><p class="text-xs text-slate-500 mt-1">Low-stock alert at or below this</p></div>
        <div><label class="label">Rack / shelf</label><input name="rack_location" value="{{ old('rack_location', $product->rack_location) }}" class="input" maxlength="50" placeholder="e.g. A-3"></div>
        <div class="md:col-span-5"><label class="label">Notes</label><textarea name="description" rows="2" class="input" maxlength="2000">{{ old('description', $product->description) }}</textarea></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_prescription" value="1" class="checkbox" @checked(old('requires_prescription', $product->requires_prescription))> Prescription required (Rx)</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $product->is_active))> Active (available for sale)</label>
    </div>

    @unless($product->exists)
        <div class="card card-body">
            <div class="font-semibold mb-1">Opening stock <span class="text-sm font-normal text-slate-500">(optional, for medicines already on your shelves)</span></div>
            <p class="text-xs text-slate-500 mb-3">For new deliveries from a supplier use <b>Stock In</b>, which records the supplier and invoice.</p>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="label">Quantity</label><input type="number" min="1" name="opening_qty" value="{{ old('opening_qty') }}" class="input"></div>
                <div><label class="label">Batch no <span class="text-slate-400 font-normal">(optional)</span></label><input name="opening_batch" value="{{ old('opening_batch') }}" class="input" maxlength="100" placeholder="Leave empty if none"></div>
                <div><label class="label">Expiry date <span class="text-slate-400 font-normal">(optional)</span></label><input type="date" name="opening_expiry" value="{{ old('opening_expiry') }}" class="input"></div>
            </div>
        </div>
    @endunless

    <div class="flex justify-between">
        <div>
            @if($product->exists)
                <button form="delete-form" class="btn btn-danger"><x-icon name="trash" class="w-4 h-4" /> Delete</button>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Save medicine</button>
        </div>
    </div>
</form>
@if($product->exists)
    <form id="delete-form" method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Delete {{ $product->name }}? Its sales history is kept.">@csrf @method('DELETE')</form>
@endif
@endsection
