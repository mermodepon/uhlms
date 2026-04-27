<?php

namespace App\Console\Commands;

use App\Notifications\FilamentDatabaseNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairNotificationLinks extends Command
{
    protected $signature = 'notifications:repair-links {--dry-run : Show what would change without saving}';

    protected $description = 'Normalize stored database notification action URLs to portable internal paths.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $notifications = DB::table('notifications')->select('id', 'data')->get();

        $inspected = 0;
        $updated = 0;

        foreach ($notifications as $notification) {
            $inspected++;

            $data = json_decode($notification->data, true);

            if (! is_array($data) || empty($data['actions']) || ! is_array($data['actions'])) {
                continue;
            }

            $changed = false;

            foreach ($data['actions'] as $index => $action) {
                $currentUrl = $action['url'] ?? null;

                if (! is_string($currentUrl) || $currentUrl === '') {
                    continue;
                }

                $normalizedUrl = FilamentDatabaseNotification::normalizeActionUrl($currentUrl);

                if ($normalizedUrl !== $currentUrl) {
                    $data['actions'][$index]['url'] = $normalizedUrl;
                    $changed = true;

                    $this->line(sprintf(
                        '%s %s: %s -> %s',
                        $dryRun ? 'Would update' : 'Updated',
                        $notification->id,
                        $currentUrl,
                        $normalizedUrl
                    ));
                }
            }

            if (! $changed) {
                continue;
            }

            $updated++;

            if ($dryRun) {
                continue;
            }

            DB::table('notifications')
                ->where('id', $notification->id)
                ->update([
                    'data' => json_encode($data, JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        $this->info("Inspected {$inspected} notifications.");
        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} notifications.");

        return self::SUCCESS;
    }
}
