@extends('layouts.app')
@section('title', 'Activity Log')

@section('content')
<div class="mb-4">
    <h2 class="page-title">Activity log</h2>
    <p class="page-subtitle">Security audit trail: logins, failed logins, sales, returns, price changes, stock changes, settings and user changes.</p>
</div>

<form class="card card-body mb-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
    <div><label class="label">User</label>
        <select name="user_id" class="input"><option value="">All</option>
            @foreach($users as $u)<option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
        </select>
    </div>
    <div><label class="label">Action</label>
        <select name="action" class="input"><option value="">All</option>
            @foreach($actions as $a)<option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>@endforeach
        </select>
    </div>
    <div><label class="label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
    <div class="flex gap-2"><button class="btn btn-dark flex-1">Filter</button><a href="{{ route('activity') }}" class="btn btn-secondary">Reset</a></div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="whitespace-nowrap">{{ $log->created_at->format('d M Y h:i:s A') }}</td>
                <td>{{ $log->user?->name ?? '—' }}</td>
                <td><span class="badge {{ str_contains($log->action, 'failed') ? 'badge-red' : (str_starts_with($log->action, 'auth') ? 'badge-blue' : 'badge-slate') }}">{{ $log->action }}</span></td>
                <td class="text-sm">{{ $log->description }}</td>
                <td class="text-xs font-mono text-slate-500">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-slate-400 py-10">No activity.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
