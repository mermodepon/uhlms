<?php

namespace App\Jobs;

use App\Services\EncryptedBackupService;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RestoreDatabaseJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        protected string $filepath,
        protected string $filename,
        protected int $initiatedById,
    ) {}

    public function handle(EncryptedBackupService $backups, SecurityAudit $audit): void
    {
        $statusFile = $backups->path('_restore_status.txt');
        $runningFile = $backups->path('_restore_running.txt');
        $snapshot = null;

        try {
            // A failed safety snapshot aborts the restore before any destructive SQL.
            $snapshot = $backups->createDatabaseBackup('pre_restore');
            $audit->record('backup_pre_restore_created', [
                'actor_id' => $this->initiatedById,
                'filename' => $snapshot,
            ]);

            $statements = $backups->restore($this->filepath);

            foreach ($backups->prune(
                'pre_restore',
                (int) config('security.backups.pre_restore_keep', 3),
                (int) config('security.backups.pre_restore_days', 7),
            ) as $deleted) {
                $audit->record('backup_retention_deleted', ['filename' => $deleted]);
            }

            $audit->alert(
                'backup_restore_completed',
                'Database restore completed',
                'The encrypted database restore completed successfully.',
                ['actor_id' => $this->initiatedById, 'filename' => $this->filename, 'count' => $statements],
                'success',
                true,
            );
            file_put_contents($statusFile, 'SUCCESS', LOCK_EX);
        } catch (Throwable $exception) {
            Log::error('Encrypted database restore failed', [
                'filename' => $this->filename,
                'error_type' => class_basename($exception),
            ]);

            try {
                $audit->alert(
                    'backup_restore_failed',
                    'Database restore failed',
                    'The encrypted database restore failed and requires review.',
                    ['actor_id' => $this->initiatedById, 'filename' => $this->filename, 'reason' => class_basename($exception)],
                    'danger',
                    true,
                );
            } catch (Throwable) {
                // The original failure remains authoritative.
            }

            file_put_contents($statusFile, 'FAILED', LOCK_EX);
            throw $exception;
        } finally {
            @unlink($runningFile);
        }
    }
}
