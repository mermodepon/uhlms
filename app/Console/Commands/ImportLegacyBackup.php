<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupService;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;

class ImportLegacyBackup extends Command
{
    protected $signature = 'backup:import-legacy {path : Absolute path to an UHLMS SQL backup} {--delete-source : Delete the plaintext only after verified encryption}';

    protected $description = 'Validate and encrypt a legacy UHLMS SQL backup without exposing SQL through the web interface';

    public function handle(EncryptedBackupService $backups, SecurityAudit $audit): int
    {
        $source = realpath((string) $this->argument('path'));
        if (! $source || ! is_file($source)) {
            $this->error('The source file does not exist.');

            return self::FAILURE;
        }

        $filename = $backups->importLegacySql($source);
        $audit->record('backup_legacy_imported', ['filename' => $filename]);

        if ($this->option('delete-source')) {
            if (! $this->confirm('Verified encryption succeeded. Permanently delete the plaintext SQL source?', false)) {
                $this->warn('The encrypted copy was retained; the plaintext source was not deleted.');

                return self::SUCCESS;
            }
            if (! unlink($source)) {
                $this->error('The encrypted copy is valid, but the plaintext source could not be deleted.');

                return self::FAILURE;
            }
            $audit->record('backup_legacy_plaintext_deleted', ['filename' => basename($source)]);
        }

        $this->info("Encrypted and verified backup: {$filename}");

        return self::SUCCESS;
    }
}
