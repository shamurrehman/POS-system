<?php

namespace App\Services;

use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Database backups. SQLite uses VACUUM INTO, which writes a consistent copy even while the
 * shop is selling. MySQL uses mysqldump (path configurable with MYSQLDUMP_PATH in .env).
 */
class BackupService
{
    public function directory(): string
    {
        $dir = config('pos.backup_path') ?: storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    public function create(): string
    {
        $driver = DB::connection()->getDriverName();
        $name = 'pharmacy-backup-'.now()->format('Y-m-d_His');

        if ($driver === 'sqlite') {
            $file = $name.'.sqlite';
            $target = $this->directory().DIRECTORY_SEPARATOR.$file;
            DB::statement('VACUUM INTO ?', [$target]);
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $file = $name.'.sql';
            $target = $this->directory().DIRECTORY_SEPARATOR.$file;
            $cfg = config('database.connections.'.config('database.default'));
            $process = new Process([
                config('pos.mysqldump_path', 'mysqldump'),
                '--host='.$cfg['host'], '--port='.$cfg['port'], '--user='.$cfg['username'],
                '--single-transaction', '--routines', '--result-file='.$target, $cfg['database'],
            ], null, ['MYSQL_PWD' => (string) $cfg['password']]);
            $process->setTimeout(600)->mustRun();
        } else {
            throw new RuntimeException("Backups are not supported for the {$driver} driver.");
        }

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('Backup file was not written.');
        }

        $this->prune();

        return $file;
    }

    /** @return list<array{name:string, size:int, time:int}> */
    public function list(): array
    {
        return collect(File::files($this->directory()))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'pharmacy-backup-'))
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => $f->getMTime()])
            ->sortByDesc('time')->values()->all();
    }

    /** Resolve a backup name to a path, refusing anything outside the backup folder. */
    public function path(string $file): ?string
    {
        if (! preg_match('/^pharmacy-backup-[0-9_\-]+\.(sqlite|sql)$/', $file)) {
            return null;
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$file;

        return is_file($path) ? $path : null;
    }

    public function hasBackupToday(): bool
    {
        return collect($this->list())->contains(fn ($b) => date('Y-m-d', $b['time']) === now()->toDateString());
    }

    /** True when the newest backup is at least BACKUP_EVERY_DAYS old (or there is none). */
    public function isBackupDue(): bool
    {
        $next = $this->nextDueDate();

        return $next === null || ! today()->lt($next);
    }

    /** Date the next automatic backup will be made, or null if there is no backup yet. */
    public function nextDueDate(): ?Carbon
    {
        $latest = $this->list()[0]['time'] ?? null;

        return $latest ? Carbon::createFromTimestamp($latest)->setTimezone(config('app.timezone'))
            ->startOfDay()->addDays(config('pos.backup_every_days')) : null;
    }

    private function prune(): void
    {
        $cutoff = now()->subDays(max(3, Settings::int('backup_keep_days')))->getTimestamp();
        // Always keep the 3 newest backups, however old they are.
        foreach (array_slice($this->list(), 3) as $b) {
            if ($b['time'] < $cutoff) {
                File::delete($this->directory().DIRECTORY_SEPARATOR.$b['name']);
            }
        }
    }
}
