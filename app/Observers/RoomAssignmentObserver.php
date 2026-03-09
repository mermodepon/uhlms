<?php

namespace App\Observers;

use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Notifications\NotificationHelper;

class RoomAssignmentObserver
{
    /**
     * NOTE: The previous "one assignment per reservation" restriction is deliberately
     * removed.  Dorm reservations support multiple guests sharing one reservation;
     * each guest receives their own RoomAssignment + Bed.
     */
    public function creating(RoomAssignment $assignment): void
    {
        // Validate that the requested bed is still available
        if ($assignment->bed_id) {
            $bed = Bed::find($assignment->bed_id);
            if (! $bed || ! in_array($bed->status, ['available', 'reserved'], true)) {
                throw new \Exception("Bed #{$assignment->bed_id} is no longer available. Please choose a different bed.");
            }
        }
    }

    public function created(RoomAssignment $assignment): void
    {
        // ✅ NEW: Mark bed as occupied when assignment is created if it has a bed
        if ($assignment->bed_id && $assignment->status === 'checked_in') {
            Bed::where('id', $assignment->bed_id)->update(['status' => 'occupied']);
        }

        // Availability/state updates are managed by explicit check-in actions.
        $room = $assignment->room_id ? Room::with('roomType')->find($assignment->room_id) : null;
        $bed = $assignment->bed_id ? Bed::find($assignment->bed_id) : null;
        $reservation = $assignment->reservation()->first();
        $bedLabel = $bed ? "Bed {$bed->bed_number} in Room {$room?->room_number}" : "Room {$room?->room_number}";

        if ($assignment->bed_id) {
            $title = 'Guest Bed Assigned';
            $message = "{$bedLabel} assigned for reservation #{$reservation->reference_number} ({$assignment->guest_first_name} {$assignment->guest_last_name}).";
        } else {
            $partySize = $reservation->number_of_occupants ?? 1;
            $title = 'Room Assigned';
            $message = "Room {$room?->room_number} assigned exclusively for reservation #{$reservation->reference_number} (party of {$partySize}).";
        }

        NotificationHelper::notifyAllStaff(
            $title,
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

        // ✅ NEW: Mark bed as occupied when status changes to checked_in
        if (array_key_exists('status', $changes) && $changes['status'] === 'checked_in' && $assignment->bed_id) {
            Bed::where('id', $assignment->bed_id)->update(['status' => 'occupied']);
        }

        if (array_key_exists('bed_id', $changes) || array_key_exists('room_id', $changes)) {
            $room = $assignment->room_id ? Room::with('roomType')->find($assignment->room_id) : null;

            $title = $room && $room->roomType?->isPrivate() ? 'Assignment Changed' : 'Bed Assignment Changed';
            $message = "Assignment for reservation #{$reservation?->reference_number} has been updated (Room {$room?->room_number}).";

            NotificationHelper::notifyAllStaff(
                $title,
                $message,
                'warning',
                'room_assignment',
                '/admin/reservations/' . $assignment->reservation_id,
                auth()->id()
            );
        }

        if (
            (array_key_exists('checked_out_at', $changes) && $changes['checked_out_at']) ||
            (array_key_exists('status', $changes) && $changes['status'] === 'checked_out')
        ) {
            if ($assignment->bed_id) {
                // Free the bed — BedObserver will automatically update dormitory room status
                Bed::where('id', $assignment->bed_id)->update(['status' => 'available']);
            }

            $room = $assignment->room_id ? Room::with('roomType')->find($assignment->room_id) : null;
            if (! $room) {
                return;
            }

            // For private rooms: update room status manually (no beds to trigger BedObserver)
            if ($room->roomType?->isPrivate()) {
                $activePrivateAssignments = RoomAssignment::query()
                    ->where('room_id', $room->id)
                    ->where('status', 'checked_in')
                    ->count();

                if ($activePrivateAssignments === 0) {
                    $room->update(['status' => 'available']);
                }
            }
            // Dormitory rooms: BedObserver handles status update automatically
        }
    }

    public function deleted(RoomAssignment $assignment): void
    {
        if ($assignment->bed_id) {
            // Free the bed — BedObserver will automatically update dormitory room status
            Bed::where('id', $assignment->bed_id)->update(['status' => 'available']);
        }

        $room = $assignment->room_id ? Room::with('roomType')->find($assignment->room_id) : null;
        $reservation = $assignment->reservation()->first();
        if ($room) {
            if ($room->roomType?->isPrivate()) {
                $activePrivateAssignments = RoomAssignment::query()
                    ->where('room_id', $room->id)
                    ->where('status', 'checked_in')
                    ->count();

                if ($activePrivateAssignments === 0) {
                    $room->update(['status' => 'available']);
                }
            }
            // Dormitory rooms: BedObserver handles status update automatically
        }

        $title = $room && $room->roomType?->isPrivate() ? 'Room Assignment Removed' : 'Bed Assignment Removed';
        $message = "Assignment for reservation #{$reservation?->reference_number} in Room {$room?->room_number} has been removed.";

        NotificationHelper::notifyAllStaff(
            $title,
            $message,
            'warning',
            'room_assignment',
            null,
            auth()->id()
        );
    }
}
