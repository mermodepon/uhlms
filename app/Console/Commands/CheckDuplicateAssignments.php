<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;

class CheckDuplicateAssignments extends Command
{
    protected $signature = 'assignments:check {--fix}';

    protected $description = 'Check for duplicate or inconsistent room assignments and optionally fix them';

    public function handle()
    {
        $reservations = Reservation::whereHas('roomAssignments')
            ->with(['roomAssignments.room', 'roomAssignments.bed'])
            ->get();

        $this->info('Checking ' . $reservations->count() . ' reservations...' . PHP_EOL);

        $issues = [];

        foreach ($reservations as $reservation) {
            $occupants = $reservation->number_of_occupants;
            $assignments = $reservation->roomAssignments->count();

            if ($assignments > $occupants) {
                $issues[] = [
                    'ref' => $reservation->reference_number,
                    'guest' => $reservation->guest_name,
                    'occupants' => $occupants,
                    'assignments' => $assignments,
                ];

                $this->warn("❌ {$reservation->reference_number} - {$reservation->guest_name}: {$assignments} assignments for {$occupants} occupants");

                // Show duplicates
                $rooms = $reservation->roomAssignments->groupBy('room_id');
                foreach ($rooms as $roomId => $group) {
                    if ($group->count() > 1) {
                        $this->line("   Room {$group[0]->room->room_number} has {$group->count()} assignments");
                        foreach ($group as $i => $assignment) {
                            $guest = trim($assignment->guest_first_name . ' ' . $assignment->guest_last_name);
                            $this->line("     {$i}. Assignment #{$assignment->id} - Guest: $guest");
                        }
                    }
                }
            }
        }

        if (empty($issues)) {
            $this->info('✓ No issues found!');
            return;
        }

        $this->error(PHP_EOL . 'Found ' . count($issues) . ' issue(s)');

        if ($this->option('fix')) {
            $this->info(PHP_EOL . 'Fixing issues...');

            foreach ($reservations as $reservation) {
                $occupants = $reservation->number_of_occupants;
                $assignments = $reservation->roomAssignments()->orderBy('created_at', 'desc')->get();

                // Keep only the first N assignments (most recent)
                if ($assignments->count() > $occupants) {
                    $toDelete = $assignments->slice($occupants);
                    foreach ($toDelete as $assignment) {
                        $guest = trim($assignment->guest_first_name . ' ' . $assignment->guest_last_name);
                        $this->line("Deleting assignment #{$assignment->id} ({$guest}) from {$reservation->reference_number}");
                        $assignment->delete();
                    }
                }
            }

            $this->info('✓ Fixed all issues!');
        } else {
            $this->comment(PHP_EOL . 'Run with --fix flag to remove duplicate assignments');
        }
    }
}
