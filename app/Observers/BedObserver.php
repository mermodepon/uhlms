<?php

namespace App\Observers;

use App\Models\Bed;
use App\Models\Room;

class BedObserver
{
    /**
     * Handle the Bed "updated" event - when status changes
     */
    public function updated(Bed $bed): void
    {
        // Only update room status if bed status changed
        if (!$bed->isDirty('status')) {
            return;
        }

        $this->updateRoomStatus($bed->room_id);
    }

    /**
     * Handle the Bed "created" event
     */
    public function created(Bed $bed): void
    {
        $this->updateRoomStatus($bed->room_id);
    }

    /**
     * Update room status based on available beds
     */
    private function updateRoomStatus(?int $roomId): void
    {
        if (!$roomId) {
            return;
        }

        $room = Room::with(['roomType', 'beds'])->find($roomId);
        if (!$room) {
            return;
        }

        // ✅ Only auto-update status for dormitory/public rooms
        // Private rooms use manual status management
        if ($room->roomType?->room_sharing_type !== 'public') {
            return;
        }

        $totalBeds = $room->beds->count();
        $availableBeds = $room->beds->where('status', 'available')->count();

        // Logic: Room is "available" if it has at least 1 free bed
        // Room is "occupied" only when all beds are taken
        $newStatus = $availableBeds > 0 ? 'available' : 'occupied';

        if ($room->status !== $newStatus) {
            $room->update(['status' => $newStatus]);
        }
    }
}
