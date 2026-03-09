<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;

class CheckBedStatus extends Command
{
    protected $signature = 'beds:check {room_number?}';

    protected $description = 'Check bed status and occupancy';

    public function handle()
    {
        $roomNumber = $this->argument('room_number') ?? 'DRM001';
        
        $room = Room::where('room_number', $roomNumber)->first();
        
        if (!$room) {
            $this->error("Room $roomNumber not found");
            return 1;
        }

        $this->info("Room: {$room->room_number}");
        $this->info("Total beds: " . $room->beds->count());
        $this->info("Available: " . $room->beds->where('status', 'available')->count());
        $this->info("Occupied: " . $room->beds->where('status', 'occupied')->count());
        $this->newLine();
        
        $this->info("Bed Details:");
        foreach ($room->beds as $bed) {
            $assignments = $bed->roomAssignments->count();
            $status = $bed->status;
            $this->line("  Bed {$bed->bed_number}: {$status} (Assignments: {$assignments})");
        }
        
        $this->newLine();
        $this->info("Room Assignments:");
        foreach ($room->roomAssignments()->with('guest')->get() as $assignment) {
            $guestName = $assignment->guest->full_name ?? 'Unknown';
            $bedNum = $assignment->bed?->bed_number ?? 'N/A';
            $this->line("  - {$guestName} (Bed {$bedNum})");
        }

        return 0;
    }
}
