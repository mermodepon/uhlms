<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeNotifications extends Command
{
    protected $signature = 'notifications:purge
                            {--all : Delete every stored database notification}
                            {--dry-run : Show how many notifications would be deleted without deleting them}';

    protected $description = 'Purge stored database notifications.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $purgeAll = (bool) $this->option('all');

        $query = DB::table('notifications');

        if (! $purgeAll) {
            $this->warn('No scope selected. Re-run with --all to purge all stored notifications.');

            return self::INVALID;
        }

        $count = (clone $query)->count();

        if ($dryRun) {
            $this->info("Would delete {$count} notifications.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} notifications.");

        return self::SUCCESS;
    }
}
