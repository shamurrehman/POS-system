<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** DatabaseMigrations (not RefreshDatabase): SQLite cannot VACUUM INTO inside a transaction. */
class BackupScheduleTest extends TestCase
{
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups');
        File::deleteDirectory($this->dir);
        config(['pos.backup_path' => $this->dir, 'pos.backup_every_days' => 7]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_weekly_backup_is_due_only_after_seven_days(): void
    {
        $backups = app(BackupService::class);
        $this->assertTrue($backups->isBackupDue(), 'no backup yet');

        $file = $backups->create();
        $this->assertFalse($backups->isBackupDue());

        $path = $this->dir.DIRECTORY_SEPARATOR.$file;
        touch($path, now()->subDays(6)->getTimestamp());
        clearstatcache();
        $this->assertFalse($backups->isBackupDue(), '6 days old: not yet');

        touch($path, now()->subDays(7)->getTimestamp());
        clearstatcache();
        $this->assertTrue($backups->isBackupDue(), '7 days old: due');

        config(['pos.backup_every_days' => 1]);
        touch($path, now()->subDay()->getTimestamp());
        clearstatcache();
        $this->assertTrue($backups->isBackupDue(), 'daily mode');
    }
}
