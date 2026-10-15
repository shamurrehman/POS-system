@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'Add user')

@section('content')
<h2 class="page-title mb-4">{{ $user->exists ? 'Edit '.$user->name : 'Add user' }}</h2>

<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card card-body grid md:grid-cols-2 gap-4 max-w-3xl" autocomplete="off">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <div><label class="label">Full name *</label><input name="name" value="{{ old('name', $user->name) }}" class="input" required maxlength="100"></div>
    <div><label class="label">Username *</label><input name="username" value="{{ old('username', $user->username) }}" class="input" required maxlength="50" pattern="[A-Za-z0-9_\-]+" title="Letters, numbers, - and _ only"></div>
    <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="input" maxlength="150"></div>
    <div><label class="label">Role *</label>
        <select name="role" class="input">
            @foreach(\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div><label class="label">{{ $user->exists ? 'New password (leave empty to keep)' : 'Password *' }}</label><input type="password" name="password" class="input" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}></div>
    <div><label class="label">Confirm password</label><input type="password" name="password_confirmation" class="input" autocomplete="new-password"></div>
    <p class="md:col-span-2 text-xs text-slate-500">Passwords need at least 8 characters with letters and numbers.</p>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $user->is_active))> Account active (can log in)</label>
    <div class="md:col-span-2 flex justify-end gap-2"><a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a><button class="btn btn-primary">Save user</button></div>
</form>
@endsection
