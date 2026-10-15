@extends('layouts.app')
@section('title', 'Backups')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">Database backups</h2>
        <p class="page-subtitle">Automatic backup: <b>{{ $everyDays == 1 ? 'every day' : ($everyDays == 7 ? 'every week' : 'every '.$everyDays.' days') }}</b>@if($nextDue) · next one on <b>{{ $nextDue->isPast() ? 'the next page opened' : $nextDue->format('d M Y') }}</b>@endif. Change the interval with <code>BACKUP_EVERY_DAYS</code> in the <code>.env</code> file. Copy backups to a USB drive or cloud folder regularly.</p>
    </div>
    <form method="POST" action="{{ route('backups.store') }}">@csrf<button class="btn btn-primary"><x-icon name="database" class="w-4 h-4" /> Back up now</button></form>
</div>

<div class="card card-body mb-4 text-sm">
    <span class="text-slate-500">Backup folder:</span> <code class="font-mono">{{ $directory }}</code>
    <span class="text-slate-500 block mt-1">To store backups somewhere else (e.g. a second drive or a Google Drive / OneDrive folder), set <code>BACKUP_PATH</code> in the <code>.env</code> file.</span>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>File</th><th>Created</th><th class="num">Size</th><th></th></tr></thead>
        <tbody>
        @forelse($backups as $b)
            <tr>
                <td class="font-mono text-sm">{{ $b['name'] }}</td>
                <td>{{ \Carbon\Carbon::createFromTimestamp($b['time'])->setTimezone(config('app.timezone'))->format('d M Y h:i A') }}</td>
                <td class="num">{{ number_format($b['size'] / 1024 / 1024, 2) }} MB</td>
                <td class="text-right"><a href="{{ route('backups.download', $b['name']) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="w-4 h-4" /> Download</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-slate-400 py-10">No backups yet. Click "Back up now".</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
