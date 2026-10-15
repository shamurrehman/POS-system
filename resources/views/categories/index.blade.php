@extends('layouts.app')
@section('title', 'Categories')

@section('content')
<div class="mb-4"><h2 class="page-title">Categories</h2><p class="page-subtitle">Group medicines for reporting, e.g. Antibiotics, Pain relief, Baby care, Surgical.</p></div>

<div class="grid lg:grid-cols-3 gap-4">
    <form method="POST" action="{{ route('categories.store') }}" class="card card-body space-y-3 self-start">
        @csrf
        <div class="font-semibold">Add category</div>
        <input name="name" class="input" placeholder="Name" required maxlength="100">
        <input name="description" class="input" placeholder="Description (optional)" maxlength="250">
        <button class="btn btn-primary w-full">Add</button>
    </form>

    <div class="card lg:col-span-2 overflow-x-auto">
        <table class="table">
            <thead><tr><th>Name</th><th>Description</th><th class="num">Products</th><th></th></tr></thead>
            <tbody>
            @forelse($categories as $c)
                <tr x-data="{ edit: false }">
                    <td colspan="2" x-show="edit" x-cloak>
                        <form method="POST" action="{{ route('categories.update', $c) }}" class="flex gap-2">@csrf @method('PUT')
                            <input name="name" value="{{ $c->name }}" class="input" required maxlength="100">
                            <input name="description" value="{{ $c->description }}" class="input" maxlength="250">
                            <button class="btn btn-primary btn-sm">Save</button>
                        </form>
                    </td>
                    <td x-show="!edit" class="font-medium">{{ $c->name }}</td>
                    <td x-show="!edit" class="text-slate-500">{{ $c->description }}</td>
                    <td class="num">{{ $c->products_count }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button type="button" class="btn btn-secondary btn-sm" @click="edit = !edit" x-text="edit ? 'Cancel' : 'Edit'"></button>
                        <form method="POST" action="{{ route('categories.destroy', $c) }}" class="inline" data-confirm="Delete category {{ $c->name }}? Products will become uncategorised.">@csrf @method('DELETE')
                            <button class="btn btn-secondary btn-sm text-red-600">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-slate-400 py-8">No categories yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
