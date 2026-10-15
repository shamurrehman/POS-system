<?php

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('pos:backup {--if-needed : Only back up when the automatic backup is due (BACKUP_EVERY_DAYS)}', function (BackupService $backups) {
    if ($this->option('if-needed') && ! $backups->isBackupDue()) {
        $this->info('No backup needed yet. Next automatic backup: '.$backups->nextDueDate()->format('d M Y').'.');

        return 0;
    }
    $file = $backups->create();
    $this->info("Backup written: {$backups->directory()}".DIRECTORY_SEPARATOR.$file);

    return 0;
})->purpose('Back up the pharmacy database');

Artisan::command('pos:create-admin {username} {--name=Administrator}', function () {
    $username = $this->argument('username');
    if (User::where('username', $username)->exists()) {
        $this->error('That username already exists.');

        return 1;
    }
    $password = $this->secret('Password (min 8 chars, letters and numbers)');
    if (strlen((string) $password) < 8 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
        $this->error('Password must be at least 8 characters with letters and numbers.');

        return 1;
    }
    User::create(['name' => $this->option('name'), 'username' => $username, 'password' => $password, 'role' => 'admin', 'is_active' => true]);
    $this->info("Admin {$username} created.");

    return 0;
})->purpose('Create an administrator account');

Artisan::command('pos:reset-password {username}', function () {
    $user = User::where('username', $this->argument('username'))->first();
    if (! $user) {
        $this->error('User not found.');

        return 1;
    }
    $password = $this->secret('New password');
    if (strlen((string) $password) < 8) {
        $this->error('Password must be at least 8 characters.');

        return 1;
    }
    $user->update(['password' => $password, 'is_active' => true]);
    $this->info('Password reset.');

    return 0;
})->purpose('Reset a user password from the server console');

Schedule::command('pos:backup --if-needed')->hourly();
