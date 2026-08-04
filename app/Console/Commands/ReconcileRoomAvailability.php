<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;

class ReconcileRoomAvailability extends Command
{
    protected $signature = 'rooms:reconcile-availability {--dry-run : Report rooms that need status correction without updating them}';

    protected $description = 'Recalculate room operational statuses from current assignments and advance holds';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        Room::query()->with('roomType')->orderBy('id')->each(function (Room $room) use ($dryRun, &$changed): void {
            $before = $room->status;

            if ($dryRun) {
                $expected = $room->calculatedOperationalStatus();

                if ($before !== $expected) {
                    $changed++;
                    $this->line("Room {$room->room_number}: {$before} -> {$expected}");
                }

                return;
            }

            $room->recalculateStatus();
            if ($before !== $room->status) {
                $changed++;
                $this->line("Room {$room->room_number}: {$before} -> {$room->status}");
            }
        });

        $this->info($dryRun
            ? "{$changed} room(s) would be reconciled."
            : "{$changed} room(s) reconciled.");

        return self::SUCCESS;
    }
}
