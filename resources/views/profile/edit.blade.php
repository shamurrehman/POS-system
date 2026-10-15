@extends('layouts.app')
@section('title', 'My profile')

@section('content')
<h2 class="page-title mb-4">My profile</h2>
<div class="grid md:grid-cols-2 gap-4 max-w-4xl">
    <div class="card card-body text-sm space-y-2">
        <div><span class="text-slate-500">Name:</span> {{ $user->name }}</div>
        <div><span class="text-slate-500">Username:</span> {{ $user->username }}</div>
        <div><span class="text-slate-500">Role:</span> {{ $user->role_label }}</div>
        <div><span class="text-slate-500">Last login:</span> {{ $user->last_login_at?->format('d M Y h:i A') }}</div>
    </div>
    <form method="POST" action="{{ route('profile.password') }}" class="card card-body space-y-3">
        @csrf @method('PUT')
        <div class="font-semibold">Change password</div>
        <div><label class="label">Current password</label><input type="password" name="current_password" class="input" required autocomplete="current-password"></div>
        <div><label class="label">New password</label><input type="password" name="password" class="input" required autocomplete="new-password"></div>
        <div><label class="label">Confirm new password</label><input type="password" name="password_confirmation" class="input" required autocomplete="new-password"></div>
        <button class="btn btn-primary">Update password</button>
    </form>
</div>
@endsection
