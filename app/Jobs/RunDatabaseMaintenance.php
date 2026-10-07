<?php

namespace App\Jobs;

use App\Models\MaintenanceRecord;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseRestoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: Back up now / Restore from the Backups page, run by the queue worker so the
 * page never waits for mysqldump. The outcome is shown on the Backups page and written to Maintenance Records.
 */
class RunDatabaseMaintenance implements ShouldQueue
{
    use Queueable;

    public const RESULT_KEY = 'admin:backups:last-result';

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public string $action, public ?string $file = null, public ?int $userId = null) {}

    public function handle(DatabaseBackupService $backups, DatabaseRestoreService $restore): void
    {
        Cache::put(self::RESULT_KEY, ['state' => 'running', 'action' => $this->action, 'at' => now()->toIso8601String()], now()->addDay());

        try {
            if ($this->action === 'restore') {
                $done = $restore->restore((string) $this->file);
                $message = 'Restored '.$done['restored'].'. The database as it was before is in '.$done['safety_backup'].'.';
            } else {
                $done = $backups->backup();
                $backups->prune();
                $message = 'Backup '.$done['file'].' made ('.number_format($done['bytes'] / 1048576, 1).' MB).';
            }
            $state = 'done';
        } catch (Throwable $e) {
            $state = 'failed';
            $message = $e->getMessage();
        }

        Cache::put(self::RESULT_KEY, ['state' => $state, 'action' => $this->action, 'message' => $message, 'at' => now()->toIso8601String()], now()->addDays(7));
        rescue(fn () => MaintenanceRecord::query()->create([
            'title' => ($this->action === 'restore' ? 'Database restore' : 'Database backup').($state === 'done' ? '' : ' (failed)'),
            'category' => 'backup',
            'description' => $message,
            'status' => 'done',
            'performed_at' => now(),
            'performed_by' => $this->userId,
        ]), report: false);
    }
}
