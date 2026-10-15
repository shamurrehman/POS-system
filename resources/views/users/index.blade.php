@extends('layouts.app')
@section('title', 'Users')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">Users & access</h2>
        <p class="page-subtitle"><b>Cashier</b>: POS, own sales, view medicines and alerts. <b>Manager</b>: also stock, purchases, returns and reports. <b>Admin</b>: everything, including users, settings and backups.</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> Add user</a>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td class="font-medium">{{ $u->name }} @if($u->id === auth()->id())<span class="badge badge-blue">You</span>@endif</td>
                <td class="font-mono text-sm">{{ $u->username }}</td>
                <td><span class="badge {{ $u->role === 'admin' ? 'badge-purple' : ($u->role === 'manager' ? 'badge-blue' : 'badge-slate') }}">{{ $u->role_label }}</span></td>
                <td>@if($u->is_active)<span class="badge badge-green">Active</span>@else<span class="badge badge-red">Disabled</span>@endif</td>
                <td>{{ $u->last_login_at?->format('d M Y h:i A') ?? 'Never' }}</td>
                <td class="text-right"><a href="{{ route('users.edit', $u) }}" class="btn btn-secondary btn-sm">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
