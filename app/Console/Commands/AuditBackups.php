<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupService;
use Illuminate\Console\Command;
use Throwable;

class AuditBackups extends Command
{
    protected $signature = 'backup:audit';

    protected $description = 'Audit UHLMS backup encryption, retention, location, and directory permissions';

    public function handle(EncryptedBackupService $backups): int
    {
        $failures = [];
        try {
            $directory = $backups->directory();
            $probe = glob($directory.DIRECTORY_SEPARATOR.'*.uhlmsbak') ?: [];
            if ($probe === []) {
                $encoded = preg_replace('/^base64:/', '', trim((string) config('security.backups.encryption_key')));
                $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
                if (! is_string($decoded) || strlen($decoded) !== 32) {
                    $failures[] = 'BACKUP_ENCRYPTION_KEY is missing or invalid.';
                }
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.sql') ?: [] as $file) {
            $failures[] = 'Plaintext SQL backup found: '.basename($file);
        }

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.uhlmsbak') ?: [] as $file) {
            try {
                $backups->validate($file);
            } catch (Throwable $exception) {
                $failures[] = basename($file).': '.$exception->getMessage();
            }
        }

        $routine = glob($directory.DIRECTORY_SEPARATOR.'backup_*.uhlmsbak') ?: [];
        $preRestore = glob($directory.DIRECTORY_SEPARATOR.'pre_restore_*.uhlmsbak') ?: [];
        if (count($routine) > (int) config('security.backups.routine_keep')) {
            $failures[] = 'Routine backup count exceeds retention.';
        }
        if (count($preRestore) > (int) config('security.backups.pre_restore_keep')) {
            $failures[] = 'Pre-restore backup count exceeds retention.';
        }
        $routineCutoff = now()->subDays((int) config('security.backups.routine_days'))->timestamp;
        foreach ($routine as $file) {
            if (filemtime($file) < $routineCutoff) {
                $failures[] = 'Routine backup exceeds age retention: '.basename($file);
            }
        }
        $preRestoreCutoff = now()->subDays((int) config('security.backups.pre_restore_days'))->timestamp;
        foreach ($preRestore as $file) {
            if (filemtime($file) < $preRestoreCutoff) {
                $failures[] = 'Pre-restore backup exceeds age retention: '.basename($file);
            }
        }

        $public = rtrim(str_replace('\\', '/', realpath(public_path()) ?: public_path()), '/').'/';
        $realDirectory = str_replace('\\', '/', realpath($directory) ?: $directory).'/';
        if (str_starts_with(strtolower($realDirectory), strtolower($public))) {
            $failures[] = 'Backup directory is web-accessible under public/.';
        }

        if (DIRECTORY_SEPARATOR === '/') {
            $permissions = fileperms($directory) & 0777;
            if ($permissions !== 0700) {
                $failures[] = sprintf('Backup directory permissions are %04o; expected 0700.', $permissions);
            }
        } else {
            $acl = (string) shell_exec('icacls '.escapeshellarg($directory).' 2>NUL');
            if (preg_match('/Authenticated Users|BUILTIN\\\\Users|Everyone/i', $acl)) {
                $failures[] = 'Backup directory ACL grants access to a broad Windows group.';
            }
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('Backup audit passed: key, encryption, retention, location, and permissions are acceptable.');

        return self::SUCCESS;
    }
}
