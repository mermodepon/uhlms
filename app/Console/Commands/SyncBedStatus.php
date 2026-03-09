<?php

namespace App\Console\Commands;

use App\Models\Bed;
use App\Models\RoomAssignment;
use Illuminate\Console\Command;

class SyncBedStatus extends Command
{
    protected $signature = 'beds:sync {--fix : Apply fixes}';

    protected $description = 'Check and sync bed status with active room assignments';

    public function handle()
    {
        // Find all beds with active checked-in assignments but marked as available
        $bedsToFix = Bed::query()
            ->where('status', 'available')
            ->whereHas('roomAssignments', function ($q) {
                $q->where('status', 'checked_in')
                  ->whereNull('checked_out_at');
            })
            ->with('roomAssignments')
            ->get();

        if ($bedsToFix->isEmpty()) {
            $this->info('✓ All beds have correct status!');
            return 0;
        }

        $this->warn("Found {$bedsToFix->count()} bed(s) with incorrect status:");
        $this->newLine();

        foreach ($bedsToFix as $bed) {
            $assignment = $bed->roomAssignments()->where('status', 'checked_in')->first();
            $guestName = $assignment?->guest?->full_name ?? 'Unknown';
            $this->line("  [{$bed->id}] {$bed->room->room_number} - {$bed->bed_number} → Assigned to {$guestName}");
        }

        if ($this->option('fix')) {
            $this->newLine();
            $this->info('Fixing...');

            $fixed = 0;
            foreach ($bedsToFix as $bed) {
                Bed::where('id', $bed->id)->update(['status' => 'occupied']);
                $fixed++;
            }

            $this->info("✓ Fixed {$fixed} bed(s)!");
        } else {
            $this->newLine();
            $this->line('Run with <fg=yellow>--fix</> flag to apply changes:');
            $this->line('<fg=cyan>  php artisan beds:sync --fix</>');
        }

        return 0;
    }
}
