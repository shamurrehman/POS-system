@extends('layouts.app')
@section('title', 'New adjustment')

@section('content')
<h2 class="page-title mb-4">Adjust stock</h2>

<form method="GET" class="card card-body mb-4 max-w-3xl" x-data="{ q: '', results: [] }">
    <label class="label">1. Choose medicine</label>
    <div class="relative">
        <input x-model="q" @input.debounce.250ms="q.length > 1 ? fetch('{{ route('pos.search') }}?q=' + encodeURIComponent(q)).then(r => r.json()).then(d => results = d) : results = []"
               class="input" placeholder="Search medicine…" value="{{ $product?->display_name }}" autofocus>
        <div x-show="results.length" x-cloak class="absolute z-20 mt-1 w-full card max-h-72 overflow-auto">
            <template x-for="p in results" :key="p.id">
                <a :href="'{{ route('adjustments.create') }}?product_id=' + p.id" class="block px-3 py-2 hover:bg-slate-50 text-sm"><span x-text="p.name"></span> <span class="text-slate-500" x-text="'· stock ' + p.stock"></span></a>
            </template>
        </div>
    </div>
</form>

@if($product)
<form method="POST" action="{{ route('adjustments.store') }}" class="card card-body max-w-3xl space-y-4">
    @csrf
    <div>
        <label class="label">2. Batch of {{ $product->display_name }}</label>
        @if($product->batches->isEmpty())
            <p class="text-sm text-slate-500">This medicine has no batches yet. Use <a class="text-brand-700 underline" href="{{ route('purchases.create') }}">Stock In</a> to receive stock first.</p>
        @else
            <div class="space-y-1">
                @foreach($product->batches as $b)
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                        <input type="radio" name="batch_id" value="{{ $b->id }}" @checked(old('batch_id', $batchId) == $b->id) required>
                        <span class="font-mono">{{ $b->batch_no }}</span>
                        <span class="text-slate-500">Exp {{ $b->expiry_date?->format('d M Y') ?? '—' }}</span>
                        @if($b->isExpired())<span class="badge badge-red">Expired</span>@endif
                        <span class="ml-auto font-medium">{{ $b->quantity }} in stock</span>
                    </label>
                @endforeach
            </div>
        @endif
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="label">3. Type</label>
            <select name="type" class="input" required>
                @foreach($types as $k => $v)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div><label class="label">Quantity</label><input type="number" name="quantity" min="1" value="{{ old('quantity') }}" class="input" required></div>
    </div>
    <div><label class="label">Reason *</label><input name="note" value="{{ old('note') }}" class="input" required maxlength="250" placeholder="e.g. Physical count correction, broken bottle, expired and destroyed"></div>
    <div class="flex justify-end gap-2"><a href="{{ route('products.show', $product) }}" class="btn btn-secondary">Cancel</a><button class="btn btn-primary">Save adjustment</button></div>
</form>
@endif
@endsection
