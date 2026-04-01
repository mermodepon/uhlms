<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    public bool $showRestoreModal = false;

    public string $restoreExistingFilename = '';

    public bool $restoreInProgress = false;

    public function mount(): void
    {
        // Detect if a restore is still running from a previous page load
        if (file_exists($this->getRestoreStatusPath())) {
            // Status file exists = restore finished while away, will be picked up by poll
            $this->restoreInProgress = true;
        } elseif (file_exists(storage_path('app/backups/_restore_task.bat'))) {
            // Batch file still exists = restore is still actively running
            $this->restoreInProgress = true;
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Get MySQL connection config.
     */
    protected function getDbConfig(): array
    {
        $connection = config('database.connections.mysql');

        return [
            'host' => $connection['host'],
            'port' => $connection['port'],
            'database' => $connection['database'],
            'username' => $connection['username'],
            'password' => $connection['password'] ?? '',
        ];
    }

    /**
     * Find the mysqldump executable path.
     */
    protected function getMysqlBinPath(string $binary): ?string
    {
        // Common XAMPP paths on Windows
        $paths = [
            'D:\\xampp\\mysql\\bin\\'.$binary.'.exe',
            'C:\\xampp\\mysql\\bin\\'.$binary.'.exe',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback: try PATH
        $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        $result = trim(shell_exec("{$which} {$binary} 2>&1") ?? '');

        if ($result && ! str_contains($result, 'Could not find') && ! str_contains($result, 'not found')) {
            return explode("\n", $result)[0];
        }

        return null;
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
     * Create a new database backup.
     */
    public function createBackup(): void
    {
        $mysqldump = $this->getMysqlBinPath('mysqldump');

        if (! $mysqldump) {
            Notification::make()
                ->title('mysqldump not found')
                ->body('Could not locate the mysqldump executable. Please check your XAMPP installation.')
                ->danger()
                ->send();

            return;
        }

        $db = $this->getDbConfig();
        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_{$timestamp}.sql";
        $filepath = $this->getBackupDir().DIRECTORY_SEPARATOR.$filename;

        // Build mysqldump command
        $cmd = sprintf(
            '"%s" --host=%s --port=%s --user=%s %s --routines --triggers --single-transaction "%s" > "%s" 2>&1',
            $mysqldump,
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['username']),
            $db['password'] !== '' ? '--password='.escapeshellarg($db['password']) : '',
            $db['database'],
            $filepath
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || ! file_exists($filepath) || filesize($filepath) === 0) {
            // Clean up empty/failed file
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            Log::error('Backup failed', ['output' => implode("\n", $output), 'code' => $returnCode]);

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
            'size' => filesize($filepath),
            'user' => auth()->user()->name,
        ]);

        Notification::make()
            ->title('Backup Created')
            ->body("Backup file: {$filename}")
            ->success()
            ->send();
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

        return response()->streamDownload(function () use ($filepath) {
            readfile($filepath);
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
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
        $this->showRestoreModal = true;
    }

    public function executeRestoreExisting(): void
    {
        if (strtoupper(trim($this->confirmPhrase)) !== 'RESTORE') {
            Notification::make()
                ->title('Confirmation Failed')
                ->body('Please type RESTORE to confirm the database restoration.')
                ->danger()
                ->send();

            return;
        }

        $mysql = $this->getMysqlBinPath('mysql');
        $mysqldump = $this->getMysqlBinPath('mysqldump');

        if (! $mysql) {
            Notification::make()
                ->title('mysql not found')
                ->body('Could not locate the mysql executable.')
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

        $db = $this->getDbConfig();
        $passwordArg = $db['password'] !== '' ? '--password='.$db['password'] : '';

        // Create an automatic pre-restore backup for safety
        if ($mysqldump) {
            $preBackupFile = $this->getBackupDir().DIRECTORY_SEPARATOR.'pre_restore_'.now()->format('Y-m-d_His').'.sql';
            $dumpCmd = sprintf(
                '"%s" --host=%s --port=%s --user=%s %s --routines --triggers --single-transaction "%s" > "%s" 2>&1',
                $mysqldump,
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['username']),
                $db['password'] !== '' ? '--password='.escapeshellarg($db['password']) : '',
                $db['database'],
                $preBackupFile
            );
            exec($dumpCmd, $dumpOutput, $dumpReturn);

            if ($dumpReturn !== 0 || ! file_exists($preBackupFile) || filesize($preBackupFile) === 0) {
                if (file_exists($preBackupFile)) {
                    unlink($preBackupFile);
                }
                Log::warning('Pre-restore backup failed, proceeding anyway', ['code' => $dumpReturn]);
            } else {
                Log::info('Pre-restore backup created', ['file' => basename($preBackupFile)]);
            }
        }

        // Build the restore as a detached background process via a batch script.
        // This ensures the mysql import runs to completion even if the PHP
        // request times out or the browser navigates away.
        $statusFile = $this->getRestoreStatusPath();
        $logFile = storage_path('app/backups/restore_log.txt');

        // Clean up any previous status file
        if (file_exists($statusFile)) {
            unlink($statusFile);
        }

        // Write a batch script that runs the import and writes a status file
        $batFile = storage_path('app/backups/_restore_task.bat');
        $batContent = '@echo off'."\r\n";
        $batContent .= sprintf(
            '"%s" --host=%s --port=%s --user=%s %s "%s" < "%s" > "%s" 2>&1',
            $mysql,
            $db['host'],
            $db['port'],
            $db['username'],
            $passwordArg,
            $db['database'],
            $filepath,
            $logFile
        )."\r\n";
        $batContent .= 'if %ERRORLEVEL% EQU 0 ('."\r\n";
        $batContent .= '  echo SUCCESS > "'.str_replace('/', '\\', $statusFile).'"'."\r\n";
        $batContent .= ') else ('."\r\n";
        $batContent .= '  echo FAILED > "'.str_replace('/', '\\', $statusFile).'"'."\r\n";
        $batContent .= ')'."\r\n";
        $batContent .= 'del "%~f0"'."\r\n"; // Self-delete the batch file

        file_put_contents($batFile, $batContent);

        // Launch detached — survives PHP process death
        $runCmd = 'start /B cmd /c "'.str_replace('/', '\\', $batFile).'"';
        pclose(popen($runCmd, 'r'));

        Log::info('Database restore started (detached)', [
            'filename' => $filename,
            'user' => auth()->user()->name,
        ]);

        $this->showRestoreModal = false;
        $this->confirmPhrase = '';
        $this->restoreExistingFilename = '';
        $this->restoreInProgress = true;

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
        $statusFile = $this->getRestoreStatusPath();

        if (! file_exists($statusFile)) {
            return; // Still running
        }

        $status = trim(file_get_contents($statusFile));
        unlink($statusFile);

        // Clean up log file
        $logFile = storage_path('app/backups/restore_log.txt');
        $logContent = file_exists($logFile) ? trim(file_get_contents($logFile)) : '';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        $this->restoreInProgress = false;

        if ($status === 'SUCCESS') {
            Log::info('Database restore completed successfully');

            Notification::make()
                ->title('Database Restored')
                ->body('The database has been successfully restored.')
                ->success()
                ->send();
        } else {
            Log::error('Database restore failed', ['log' => $logContent]);

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
