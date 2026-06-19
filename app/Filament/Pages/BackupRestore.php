<?php

namespace App\Filament\Pages;

use App\Jobs\RestoreDatabaseJob;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $statusFile  = $this->getRestoreStatusPath();
        $runningFile = storage_path('app/backups/_restore_running.txt');

        if (file_exists($statusFile)) {
            // A previous restore finished while the user was away.
            // Consume the status file immediately so it doesn't look
            // like opening the page triggered a new restore.
            $status = trim(file_get_contents($statusFile));
            unlink($statusFile);

            if ($status === 'SUCCESS') {
                Log::info('Previous database restore completed successfully (detected on page load)');
                Notification::make()
                    ->title('Previous Restore Completed')
                    ->body('A previously initiated database restore completed successfully.')
                    ->success()
                    ->send();
            } else {
                Log::error('Previous database restore failed (detected on page load)');
                Notification::make()
                    ->title('Previous Restore Failed')
                    ->body('A previously initiated database restore failed. Check logs for details.')
                    ->danger()
                    ->send();
            }
        } elseif (file_exists($runningFile)) {
            // Running file exists = restore job is still active
            $this->restoreInProgress = true;
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }


    /**
     * Get backup storage directory path.
     */
    protected function getBackupDir(): string
    {
        $dir = storage_path('app/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * List existing backups.
     */
    public function getBackupsProperty(): array
    {
        $dir = $this->getBackupDir();
        $files = glob($dir.DIRECTORY_SEPARATOR.'*.sql');

        if (! $files) {
            return [];
        }

        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'created_at' => filemtime($file),
            ];
        }

        // Sort newest first
        usort($backups, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    /**
     * Create a new database backup using PDO (no external binaries required).
     */
    public function createBackup(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Notification::make()
                ->title('Not Available')
                ->body('Backup requires a MySQL database connection.')
                ->warning()
                ->send();

            return;
        }

        $timestamp = now()->format('Y-m-d_His');
        $filename  = "backup_{$timestamp}.sql";
        $filepath  = $this->getBackupDir().DIRECTORY_SEPARATOR.$filename;

        try {
            $this->exportDatabaseToFile($filepath);
        } catch (\Throwable $e) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            Log::error('Backup failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Backup Failed')
                ->body('An error occurred while creating the backup. Check logs for details.')
                ->danger()
                ->send();

            return;
        }

        // Prune old backups (keep last 10)
        $this->pruneOldBackups(10);

        Log::info('Database backup created', [
            'filename' => $filename,
            'size'     => filesize($filepath),
            'user'     => auth()->user()->name,
        ]);

        Notification::make()
            ->title('Backup Created')
            ->body("Backup file: {$filename}")
            ->success()
            ->send();
    }

    /**
     * Export the current database to a SQL file using PDO.
     * Works on any hosting environment — no external binaries needed.
     */
    private function exportDatabaseToFile(string $filepath): void
    {
        $pdo = DB::getPdo();
        $fp  = fopen($filepath, 'w');

        if (! $fp) {
            throw new \RuntimeException("Cannot open file for writing: {$filepath}");
        }

        try {
            fwrite($fp, "-- UHLMS Database Backup\n");
            fwrite($fp, '-- Generated: '.now()->toDateTimeString()."\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            /** @var string[] $tables */
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $quoted    = '`'.str_replace('`', '``', $table).'`';
                $createRow = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(\PDO::FETCH_ASSOC);
                $createSql = $createRow['Create Table'];

                fwrite($fp, "DROP TABLE IF EXISTS {$quoted};\n");
                fwrite($fp, $createSql.";\n\n");

                $stmt = $pdo->query("SELECT * FROM {$quoted}");
                $cols = null;
                $rows = [];

                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($cols === null) {
                        $cols = array_map(
                            fn ($c) => '`'.str_replace('`', '``', $c).'`',
                            array_keys($row)
                        );
                    }

                    $rows[] = '('.implode(', ', array_map(
                        fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($row)
                    )).')';

                    if (count($rows) >= 500) {
                        fwrite($fp, 'INSERT INTO '.$quoted.' ('.implode(', ', $cols).") VALUES\n".implode(",\n", $rows).";\n");
                        $rows = [];
                    }
                }

                if (! empty($rows)) {
                    fwrite($fp, 'INSERT INTO '.$quoted.' ('.implode(', ', $cols).") VALUES\n".implode(",\n", $rows).";\n");
                }

                fwrite($fp, "\n");
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($fp);
        }
    }

    /**
     * Download a backup file.
     */
    public function downloadBackup(string $filename): StreamedResponse
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filepath = $this->getBackupDir().DIRECTORY_SEPARATOR.$filename;

        if (! file_exists($filepath)) {
            Notification::make()
                ->title('File Not Found')
                ->body('The backup file does not exist.')
                ->danger()
                ->send();
            abort(404);
        }

        Log::info('Database backup downloaded', [
            'filename' => $filename,
            'user' => auth()->user()?->name,
        ]);

        return response()->streamDownload(function () use ($filepath) {
            readfile($filepath);
        }, $filename, [
            'Content-Type' => 'application/sql',
        ])->deleteFileAfterSend(false);
    }

    /**
     * Delete a backup file.
     */
    public function deleteBackup(string $filename): void
    {
        $filename = basename($filename);
        $filepath = $this->getBackupDir().DIRECTORY_SEPARATOR.$filename;

        if (file_exists($filepath)) {
            unlink($filepath);

            Log::info('Backup deleted', [
                'filename' => $filename,
                'user' => auth()->user()->name,
            ]);

            Notification::make()
                ->title('Backup Deleted')
                ->body("Deleted: {$filename}")
                ->success()
                ->send();
        }
    }

    /**
     * Cancel restore.
     */
    public function cancelRestore(): void
    {
        $this->showRestoreModal = false;
        $this->confirmPhrase = '';
        $this->restorePassword = '';
        $this->restoreExistingFilename = '';
    }

    /**
     * Restore from an existing backup file on disk.
     */
    public function restoreFromExisting(string $filename): void
    {
        $filename = basename($filename);
        $filepath = $this->getBackupDir().DIRECTORY_SEPARATOR.$filename;

        if (! file_exists($filepath)) {
            Notification::make()
                ->title('File Not Found')
                ->body('The backup file does not exist.')
                ->danger()
                ->send();

            return;
        }

        $this->restoreExistingFilename = $filename;
        $this->confirmPhrase = '';
        $this->restorePassword = '';
        $this->showRestoreModal = true;

        Log::info('Database restore confirmation opened', [
            'filename' => $filename,
            'user' => auth()->user()?->name,
        ]);
    }

    public function executeRestoreExisting(): void
    {
        $this->validate([
            'confirmPhrase' => ['required', 'in:RESTORE'],
            'restorePassword' => ['required', 'current_password'],
        ]);

        if (strtoupper(trim($this->confirmPhrase)) !== 'RESTORE') {
            Notification::make()
                ->title('Confirmation Failed')
                ->body('Please type RESTORE to confirm the database restoration.')
                ->danger()
                ->send();

            return;
        }

        $filename = basename($this->restoreExistingFilename);
        $filepath = $this->getBackupDir().DIRECTORY_SEPARATOR.$filename;

        if (! file_exists($filepath)) {
            Notification::make()
                ->title('File Not Found')
                ->danger()
                ->send();

            return;
        }

        // Clean up any leftover status file from a previous run
        $statusFile  = $this->getRestoreStatusPath();
        $runningFile = storage_path('app/backups/_restore_running.txt');

        if (file_exists($statusFile)) {
            unlink($statusFile);
        }

        // Mark restore as in-progress before dispatching so mount() and
        // pollRestoreStatus() can detect it immediately.
        file_put_contents($runningFile, now()->toDateTimeString());

        RestoreDatabaseJob::dispatch(
            filepath:    $filepath,
            filename:    $filename,
            initiatedBy: auth()->user()->name,
        );

        Log::info('Database restore job dispatched', [
            'filename' => $filename,
            'user'     => auth()->user()->name,
        ]);

        $this->showRestoreModal       = false;
        $this->confirmPhrase          = '';
        $this->restorePassword        = '';
        $this->restoreExistingFilename = '';
        $this->restoreInProgress      = true;

        Notification::make()
            ->title('Restore In Progress')
            ->body('The database is being restored in the background. Please wait...')
            ->warning()
            ->send();
    }

    /**
     * Get the path to the restore status file.
     */
    protected function getRestoreStatusPath(): string
    {
        return storage_path('app/backups/_restore_status.txt');
    }

    /**
     * Poll for restore completion. Called by wire:poll on the UI.
     */
    public function pollRestoreStatus(): void
    {
        $statusFile  = $this->getRestoreStatusPath();
        $runningFile = storage_path('app/backups/_restore_running.txt');

        if (! file_exists($statusFile)) {
            // If the running file is also gone the job finished without writing
            // a status file (e.g. worker crash). Surface an error to the user.
            if (! file_exists($runningFile)) {
                $this->restoreInProgress = false;
                Log::error('Restore job ended without writing a status file');
                Notification::make()
                    ->title('Restore Status Unknown')
                    ->body('The restore process ended unexpectedly. Please check the database and logs.')
                    ->danger()
                    ->send();
            }

            return; // Still running or handled above
        }

        $status = trim(file_get_contents($statusFile));
        unlink($statusFile);

        $this->restoreInProgress = false;

        if ($status === 'SUCCESS') {
            Log::info('Database restore completed successfully');

            Notification::make()
                ->title('Database Restored')
                ->body('The database has been successfully restored.')
                ->success()
                ->send();
        } else {
            Log::error('Database restore failed');

            Notification::make()
                ->title('Restore Failed')
                ->body('An error occurred during restoration. Check logs for details.')
                ->danger()
                ->send();
        }
    }

    /**
     * Keep only the latest N backups.
     */
    protected function pruneOldBackups(int $keep = 10): void
    {
        $dir = $this->getBackupDir();
        $files = glob($dir.DIRECTORY_SEPARATOR.'*.sql');

        if (! $files || count($files) <= $keep) {
            return;
        }

        // Sort by modification time, newest first
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        // Delete files beyond the keep limit
        foreach (array_slice($files, $keep) as $file) {
            unlink($file);
        }
    }

    /**
     * Format file size for display.
     */
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
}
