@extends('layouts.app')
@section('title', $supplier->exists ? 'Edit supplier' : 'Add supplier')

@section('content')
<h2 class="page-title mb-4">{{ $supplier->exists ? 'Edit '.$supplier->name : 'Add supplier' }}</h2>

<form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card card-body grid md:grid-cols-2 gap-4 max-w-3xl">
    @csrf
    @if($supplier->exists) @method('PUT') @endif
    <div class="md:col-span-2"><label class="label">Company name *</label><input name="name" value="{{ old('name', $supplier->name) }}" class="input" required maxlength="150"></div>
    <div><label class="label">Contact person</label><input name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" class="input" maxlength="150"></div>
    <div><label class="label">Phone</label><input name="phone" value="{{ old('phone', $supplier->phone) }}" class="input" maxlength="50"></div>
    <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="input" maxlength="150"></div>
    <div><label class="label">Address</label><input name="address" value="{{ old('address', $supplier->address) }}" class="input" maxlength="250"></div>
    <div class="md:col-span-2"><label class="label">Notes</label><textarea name="notes" rows="3" class="input" maxlength="2000">{{ old('notes', $supplier->notes) }}</textarea></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $supplier->is_active))> Active</label>
    <div class="md:col-span-2 flex justify-between">
        <div>@if($supplier->exists)<button form="del" class="btn btn-danger">Delete</button>@endif</div>
        <div class="flex gap-2"><a href="{{ route('suppliers.index') }}" class="btn btn-secondary">Cancel</a><button class="btn btn-primary">Save</button></div>
    </div>
</form>
@if($supplier->exists)
    <form id="del" method="POST" action="{{ route('suppliers.destroy', $supplier) }}" data-confirm="Remove this supplier? Past purchases are kept.">@csrf @method('DELETE')</form>
@endif
@endsection
