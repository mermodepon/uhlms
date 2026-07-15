<?php

namespace App\Filament\Pages;

use App\Jobs\RestoreDatabaseJob;
use App\Services\EncryptedBackupService;
use App\Support\AdminMfa;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupRestore extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Backup & Restore';

    protected static ?string $navigationLabel = 'Backup & Restore';

    protected static string $view = 'filament.pages.backup-restore';

    public string $confirmPhrase = '';

    public string $restorePassword = '';

    public bool $showRestoreModal = false;

    public string $restoreExistingFilename = '';

    public bool $restoreInProgress = false;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! AdminMfa::isEnabled(auth()->user())) {
            $this->redirectRoute('admin.mfa.setup');

            return;
        }
        if (! AdminMfa::isRecent()) {
            $this->redirectRoute('admin.mfa.recent', ['intended' => '/admin/backup-restore']);

            return;
        }

        $statusFile = $this->restoreStatusPath();
        $runningFile = $this->restoreRunningPath();
        if (file_exists($statusFile)) {
            $status = trim((string) file_get_contents($statusFile));
            @unlink($statusFile);
            Notification::make()
                ->title($status === 'SUCCESS' ? 'Previous Restore Completed' : 'Previous Restore Failed')
                ->body($status === 'SUCCESS'
                    ? 'A previously initiated database restore completed successfully.'
                    : 'A previously initiated database restore failed. Check the security log.')
                ->status($status === 'SUCCESS' ? 'success' : 'danger')
                ->send();
        } elseif (file_exists($runningFile)) {
            $this->restoreInProgress = true;
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getBackupsProperty(): array
    {
        $this->authorizeSensitiveAction();
        $files = glob(app(EncryptedBackupService::class)->directory().DIRECTORY_SEPARATOR.'*.uhlmsbak') ?: [];
        $backups = array_map(fn (string $file): array => [
            'filename' => basename($file),
            'size' => filesize($file),
            'created_at' => filemtime($file),
        ], $files);
        usort($backups, fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    public function createBackup(EncryptedBackupService $backups, SecurityAudit $audit): void
    {
        $this->authorizeSensitiveAction();
        $filename = null;

        try {
            $filename = $backups->createDatabaseBackup('backup');
            $audit->alert(
                'backup_created',
                'Database backup created',
                'A new encrypted database backup was created.',
                ['actor_id' => auth()->id(), 'filename' => $filename],
                'success',
                true,
            );
            foreach ($backups->prune('backup', (int) config('security.backups.routine_keep'), (int) config('security.backups.routine_days')) as $deleted) {
                $audit->record('backup_retention_deleted', ['filename' => $deleted]);
            }
        } catch (Throwable $exception) {
            if ($filename) {
                @unlink($backups->path($filename));
            }
            try {
                $audit->alert(
                    'backup_create_failed',
                    'Database backup failed',
                    'An encrypted database backup could not be created.',
                    ['actor_id' => auth()->id(), 'filename' => $filename, 'reason' => class_basename($exception)],
                    'danger',
                    true,
                );
            } catch (Throwable) {
                // The original fail-closed error remains authoritative.
            }
            Log::error('Encrypted backup creation failed', ['error_type' => class_basename($exception)]);
            Notification::make()->title('Backup Failed')->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Backup Created')->body("Encrypted backup: {$filename}")->success()->send();
    }

    public function downloadBackup(string $filename, EncryptedBackupService $backups, SecurityAudit $audit): StreamedResponse
    {
        $this->authorizeSensitiveAction();
        $filename = basename($filename);
        abort_unless(str_ends_with($filename, '.uhlmsbak'), 404);
        $path = $backups->path($filename);
        abort_unless(is_file($path), 404);

        $audit->alert(
            'backup_downloaded',
            'Database backup downloaded',
            'An encrypted database backup was downloaded.',
            ['actor_id' => auth()->id(), 'filename' => $filename],
            'warning',
            true,
        );

        return response()->streamDownload(static function () use ($path): void {
            $handle = fopen($path, 'rb');
            fpassthru($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    public function deleteBackup(string $filename, EncryptedBackupService $backups, SecurityAudit $audit): void
    {
        $this->authorizeSensitiveAction();
        $filename = basename($filename);
        abort_unless(str_ends_with($filename, '.uhlmsbak'), 404);
        $path = $backups->path($filename);
        abort_unless(is_file($path), 404);

        $audit->alert(
            'backup_delete_requested',
            'Database backup deleted',
            'An encrypted database backup was deleted.',
            ['actor_id' => auth()->id(), 'filename' => $filename],
            'warning',
            true,
        );
        if (! @unlink($path)) {
            throw new \RuntimeException('The encrypted backup could not be deleted.');
        }
        $audit->record('backup_deleted', ['actor_id' => auth()->id(), 'filename' => $filename]);

        Notification::make()->title('Backup Deleted')->body("Deleted: {$filename}")->success()->send();
    }

    public function cancelRestore(): void
    {
        $this->authorizeSensitiveAction();
        $this->reset(['showRestoreModal', 'confirmPhrase', 'restorePassword', 'restoreExistingFilename']);
    }

    public function restoreFromExisting(string $filename, EncryptedBackupService $backups): void
    {
        $this->authorizeSensitiveAction();
        $filename = basename($filename);
        abort_unless(str_ends_with($filename, '.uhlmsbak') && is_file($backups->path($filename)), 404);

        $this->restoreExistingFilename = $filename;
        $this->confirmPhrase = '';
        $this->restorePassword = '';
        $this->showRestoreModal = true;
    }

    public function executeRestoreExisting(EncryptedBackupService $backups, SecurityAudit $audit): void
    {
        $this->authorizeSensitiveAction();
        $this->validate([
            'confirmPhrase' => ['required', 'in:RESTORE'],
            'restorePassword' => ['required', 'current_password'],
        ]);

        $filename = basename($this->restoreExistingFilename);
        abort_unless(str_ends_with($filename, '.uhlmsbak'), 404);
        $path = $backups->path($filename);
        abort_unless(is_file($path), 404);
        $backups->validate($path);

        $audit->alert(
            'backup_restore_requested',
            'Database restore requested',
            'A restore from an encrypted database backup was requested.',
            ['actor_id' => auth()->id(), 'filename' => $filename],
            'danger',
            true,
        );

        @unlink($this->restoreStatusPath());
        if (file_put_contents($this->restoreRunningPath(), now()->toIso8601String(), LOCK_EX) === false) {
            throw new \RuntimeException('The restore status marker could not be created.');
        }

        RestoreDatabaseJob::dispatch($path, $filename, (int) auth()->id());

        $this->showRestoreModal = false;
        $this->confirmPhrase = '';
        $this->restorePassword = '';
        $this->restoreExistingFilename = '';
        $this->restoreInProgress = true;
        Notification::make()->title('Restore In Progress')->body('A safety snapshot is being created before restoration.')->warning()->send();
    }

    public function pollRestoreStatus(): void
    {
        $this->authorizeSensitiveAction();
        if (! is_file($this->restoreStatusPath())) {
            return;
        }
        $status = trim((string) file_get_contents($this->restoreStatusPath()));
        @unlink($this->restoreStatusPath());
        $this->restoreInProgress = false;
        Notification::make()
            ->title($status === 'SUCCESS' ? 'Database Restored' : 'Restore Failed')
            ->body($status === 'SUCCESS' ? 'The database was restored successfully.' : 'Check the dedicated security log.')
            ->status($status === 'SUCCESS' ? 'success' : 'danger')
            ->send();
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private function authorizeSensitiveAction(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(AdminMfa::isEnabled(auth()->user()) && AdminMfa::isRecent(), 403, 'Recent MFA confirmation is required.');
    }

    private function restoreStatusPath(): string
    {
        return app(EncryptedBackupService::class)->path('_restore_status.txt');
    }

    private function restoreRunningPath(): string
    {
        return app(EncryptedBackupService::class)->path('_restore_running.txt');
    }
}
