<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index()
    {
        return view('backups.index', [
            'backups' => $this->backups->list(),
            'directory' => $this->backups->directory(),
            'everyDays' => config('pos.backup_every_days'),
            'nextDue' => $this->backups->nextDueDate(),
        ]);
    }

    public function store()
    {
        try {
            $file = $this->backups->create();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Backup failed: '.$e->getMessage());
        }
        ActivityLog::record('backup.created', "Created backup {$file}");

        return back()->with('success', "Backup created: {$file}");
    }

    public function download(Request $request, string $file)
    {
        $path = $this->backups->path($file);
        abort_unless($path, 404);
        ActivityLog::record('backup.downloaded', "Downloaded backup {$file}");

        return response()->download($path);
    }
}
