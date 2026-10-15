<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Services\BackupService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes the database backup automatically every BACKUP_EVERY_DAYS days (see .env), no matter
 * how the POS was started (XAMPP Control Panel, Apache service or start-pos.bat). Runs after
 * the response, checks at most once per day, and never breaks the page if the backup fails.
 */
class AutoBackup
{
    public function __construct(private BackupService $backups) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        // Only the first request of the day does the (cheap) check; Cache::add is atomic.
        if (! Cache::add('auto_backup_checked:'.now()->toDateString(), true, now()->addDay())) {
            return;
        }

        try {
            if ($this->backups->isBackupDue()) {
                $file = $this->backups->create();
                ActivityLog::record('backup.auto', "Automatic backup {$file}", null, null);
            }
        } catch (Throwable $e) {
            Cache::forget('auto_backup_checked:'.now()->toDateString()); // try again on a later request
            report($e);
        }
    }
}
