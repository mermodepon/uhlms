<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;

class SyncRoomStatus extends Command
{
    protected $signature = 'rooms:sync-status {--fix : Apply fixes}';

    protected $description = 'Sync room status based on available beds for dormitory rooms';

    public function handle()
    {
        // Get all dormitory rooms
        $rooms = Room::query()
            ->whereHas('roomType', fn ($q) => $q->where('room_sharing_type', 'public'))
            ->with(['roomType', 'beds'])
            ->get();

        $needsFixing = 0;
        $fixes = [];

        foreach ($rooms as $room) {
            $availableBeds = $room->beds->where('status', 'available')->count();
            $expectedStatus = $availableBeds > 0 ? 'available' : 'occupied';

            if ($room->status !== $expectedStatus) {
                $needsFixing++;
                $fixes[] = [
                    'room' => $room->room_number,
                    'current' => $room->status,
                    'expected' => $expectedStatus,
                    'available_beds' => $availableBeds,
                    'total_beds' => $room->beds->count(),
                ];
            }
        }

        if ($needsFixing === 0) {
            $this->info('✓ All dormitory room statuses are correct!');
            return 0;
        }

        $this->warn("Found {$needsFixing} room(s) with incorrect status:");
        $this->newLine();

        $this->table(
            ['Room', 'Current', 'Expected', 'Available Beds', 'Total Beds'],
            $fixes
        );

        if ($this->option('fix')) {
            $this->newLine();
            $this->info('Fixing...');

            $fixed = 0;
            foreach ($fixes as $fix) {
                Room::where('room_number', $fix['room'])
                    ->update(['status' => $fix['expected']]);
                $fixed++;
            }

            $this->info("✓ Fixed {$fixed} room(s)!");
        } else {
            $this->newLine();
            $this->line('Run with <fg=yellow>--fix</> flag to apply changes:');
            $this->line('<fg=cyan>  php artisan rooms:sync-status --fix</>');
        }

        return 0;
    }
}
