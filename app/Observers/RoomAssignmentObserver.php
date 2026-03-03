<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\RoomAssignment;
use App\Models\User;

class RoomAssignmentObserver
{
    public function creating(RoomAssignment $assignment): void
    {
        // Enforce only one room assignment per reservation
        $existingAssignmentCount = RoomAssignment::where('reservation_id', $assignment->reservation_id)->count();
        
        if ($existingAssignmentCount > 0) {
            throw new \Exception('Only one room assignment is allowed per reservation.');
        }
    }

    public function created(RoomAssignment $assignment): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Room Assigned',
                "Room {$assignment->room->room_number} has been assigned for reservation #{$assignment->reservation->reference_number}.",
                'info',
                'room_assignment',
                '/admin/reservations/' . $assignment->reservation->id,
                auth()->id()
            );
        }
    }

    public function updated(RoomAssignment $assignment): void
    {
        $changes = $assignment->getChanges();
        
        if (array_key_exists('room_id', $changes)) {
            $newRoom = $assignment->room;
            
            $staff = User::whereIn('role', ['admin', 'staff'])->get();
            foreach ($staff as $user) {
                Notification::createNotification(
                    $user,
                    'Room Assignment Changed',
                    "Room for reservation #{$assignment->reservation->reference_number} has been changed to {$newRoom->room_number}.",
                    'warning',
                    'room_assignment',
                    '/admin/reservations/' . $assignment->reservation->id,
                    auth()->id()
                );
            }
        }
    }

    public function deleted(RoomAssignment $assignment): void
    {
        // Free the room when unassigned (only if not currently checked in)
        if ($assignment->room && in_array($assignment->room->status, ['occupied'])) {
            $reservation = $assignment->reservation;
            // Only mark available if the reservation is not actively checked in
            if (!$reservation || $reservation->status !== 'checked_in') {
                $assignment->room->update(['status' => 'available']);
            }
        }

        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Room Assignment Removed',
                "Room {$assignment->room->room_number} assignment for reservation #{$assignment->reservation->reference_number} has been removed.",
                'warning',
                'room_assignment',
                null,
                auth()->id()
            );
        }
    }
}
