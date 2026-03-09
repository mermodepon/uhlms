<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\RoomAssignment;
use Illuminate\Console\Command;

class CleanDuplicateAssignments extends Command
{
    protected $signature = 'assignments:clean-duplicates {reservation_id} {--fix : Apply fixes}';

    protected $description = 'Remove duplicate room assignments for a reservation';

    public function handle()
    {
        $reservationId = $this->argument('reservation_id');
        $reservation = Reservation::with(['roomAssignments.room', 'roomAssignments.guest'])->find($reservationId);

        if (!$reservation) {
            $this->error("Reservation #{$reservationId} not found");
            return 1;
        }

        $this->info("Reservation: {$reservation->reference_number}");
        $this->info("Expected occupants: {$reservation->number_of_occupants}");
        $this->info("Current assignments: " . $reservation->roomAssignments->count());
        $this->newLine();

        // Group by guest name to find duplicates
        $grouped = $reservation->roomAssignments->groupBy(function ($assignment) {
            return trim($assignment->guest_first_name . ' ' . $assignment->guest_last_name);
        });

        $duplicates = [];
        foreach ($grouped as $guestName => $assignments) {
            if ($assignments->count() > 1) {
                $duplicates[$guestName] = $assignments;
                $this->warn("Duplicate found: {$guestName} has {$assignments->count()} assignments");
                foreach ($assignments as $a) {
                    $this->line("  ID:{$a->id} | Room:{$a->room->room_number} | Bed:{$a->bed_id} | Created:{$a->created_at}");
                }
            }
        }

        if (empty($duplicates)) {
            $this->info('✓ No duplicates found!');
            return 0;
        }

        if ($this->option('fix')) {
            $this->newLine();
            $this->info('Fixing duplicates - keeping the first assignment for each guest...');

            $deleted = 0;
            foreach ($duplicates as $guestName => $assignments) {
                // Keep the first (oldest) assignment, delete the rest
                $toKeep = $assignments->sortBy('created_at')->first();
                $toDelete = $assignments->filter(fn($a) => $a->id !== $toKeep->id);

                foreach ($toDelete as $assignment) {
                    $this->line("  Deleting assignment #{$assignment->id} for {$guestName} (Room {$assignment->room->room_number}, Bed {$assignment->bed_id})");
                    $assignment->delete();
                    $deleted++;
                }
            }

            $this->newLine();
            $this->info("✓ Deleted {$deleted} duplicate assignment(s)!");
            $this->info("Final count: " . Reservation::find($reservationId)->roomAssignments()->count() . " assignments");
        } else {
            $this->newLine();
            $this->line('Run with <fg=yellow>--fix</> flag to remove duplicates:');
            $this->line("<fg=cyan>  php artisan assignments:clean-duplicates {$reservationId} --fix</>");
        }

        return 0;
    }
}
