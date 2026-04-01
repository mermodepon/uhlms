<?php

namespace App\Observers;

use App\Models\Room;
use App\Models\ReservationLog;
use App\Models\RoomAssignment;
use App\Notifications\NotificationHelper;

class RoomAssignmentObserver
{
    public function created(RoomAssignment $assignment): void
    {
        if ($assignment->status === 'checked_in' && $assignment->room_id) {
            $room = Room::with('roomType')->find($assignment->room_id);
            if ($room) {
                $this->updateRoomStatus($room);
            }
        }

        $room = $assignment->room_id ? Room::find($assignment->room_id) : null;
        $reservation = $assignment->reservation()->first();

        $guestName = trim("{$assignment->guest_first_name} {$assignment->guest_last_name}");

        ReservationLog::record(
            $assignment->reservation_id,
            'guest_checked_in',
            "Guest {$guestName} checked into Room {$room?->room_number}.",
            [
                'room_number'  => $room?->room_number,
                'assignment_id' => $assignment->id,
            ]
        );

        $message = "Room {$room?->room_number} assigned for reservation #{$reservation->reference_number} "
            . "({$assignment->guest_first_name} {$assignment->guest_last_name}).";

        NotificationHelper::notifyAllStaff(
            'Room Assigned',
            $message,
            'info',
            'room_assignment',
            '/admin/reservations/' . $assignment->reservation_id,
            auth()->id()
        );
    }

    public function updated(RoomAssignment $assignment): void
    {
        $changes = $assignment->getChanges();
        $reservation = $assignment->reservation()->first();

        // When status changes to checked_in, update room status
        if (array_key_exists('status', $changes) && $changes['status'] === 'checked_in' && $assignment->room_id) {
            $room = Room::with('roomType')->find($assignment->room_id);
            if ($room) {
                $this->updateRoomStatus($room);
            }
        }

        if (array_key_exists('room_id', $changes)) {
            $room = $assignment->room_id ? Room::with('roomType')->find($assignment->room_id) : null;

            NotificationHelper::notifyAllStaff(
                'Assignment Changed',
                "Assignment for reservation #{$reservation?->reference_number} has been updated (Room {$room?->room_number}).",
                'warning',
                'room_assignment',
                '/admin/reservations/' . $assignment->reservation_id,
                auth()->id()
            );
        }

        // When checked out, release the room if no more active guests
        if (
            (array_key_exists('checked_out_at', $changes) && $changes['checked_out_at']) ||
            (array_key_exists('status', $changes) && $changes['status'] === 'checked_out')
        ) {
            $room = $assignment->room_id ? Room::with('roomType')->find($assignment->room_id) : null;
            if ($room) {
                $this->updateRoomStatus($room);
            }

            $guestName = trim("{$assignment->guest_first_name} {$assignment->guest_last_name}");
            ReservationLog::record(
                $assignment->reservation_id,
                'guest_checked_out',
                "Guest {$guestName} checked out of Room {$room?->room_number}.",
                [
                    'room_number'   => $room?->room_number,
                    'assignment_id' => $assignment->id,
                    'checked_out_at' => $assignment->checked_out_at?->toDateTimeString(),
                ]
            );
        }
    }

    public function deleted(RoomAssignment $assignment): void
    {
        $room = $assignment->room_id ? Room::with('roomType')->find($assignment->room_id) : null;
        $reservation = $assignment->reservation()->first();

        if ($room) {
            $this->updateRoomStatus($room);
        }

        $guestName = trim("{$assignment->guest_first_name} {$assignment->guest_last_name}");
        ReservationLog::record(
            $assignment->reservation_id,
            'room_assignment_removed',
            "Room assignment removed: Guest {$guestName} from Room {$room?->room_number}.",
            [
                'room_number'   => $room?->room_number,
                'assignment_id' => $assignment->id,
            ]
        );

        NotificationHelper::notifyAllStaff(
            'Room Assignment Removed',
            "Assignment for reservation #{$reservation?->reference_number} in Room {$room?->room_number} has been removed.",
            'warning',
            'room_assignment',
            null,
            auth()->id()
        );
    }

    /**
     * Delegate status recalculation to the Room model.
     */
    private function updateRoomStatus(Room $room): void
    {
        $room->recalculateStatus();
    }
}

