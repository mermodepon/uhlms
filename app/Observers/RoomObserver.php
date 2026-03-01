<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\Room;
use App\Models\User;

class RoomObserver
{
    public function created(Room $room): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'New Room Created',
                "Room {$room->room_number} ({$room->roomType->name}) has been added to the system.",
                'success',
                'room',
                '/admin/rooms/' . $room->id,
                auth()->id()
            );
        }
    }

    public function updated(Room $room): void
    {
        $changes = $room->getChanges();
        
        if (array_key_exists('status', $changes)) {
            $newStatus = $changes['status'];
            $oldStatus = $room->getOriginal('status');
            
            $staff = User::whereIn('role', ['admin', 'staff'])->get();
            foreach ($staff as $user) {
                Notification::createNotification(
                    $user,
                    'Room Status Changed',
                    "Room {$room->room_number} status changed from {$oldStatus} to {$newStatus}.",
                    $this->getStatusNotificationType($newStatus),
                    'room',
                    '/admin/rooms/' . $room->id,
                    auth()->id()
                );
            }
        }
        
        if (array_key_exists('is_active', $changes)) {
            $isActive = $changes['is_active'];
            $status = $isActive ? 'activated' : 'deactivated';
            
            $staff = User::whereIn('role', ['admin', 'staff'])->get();
            foreach ($staff as $user) {
                Notification::createNotification(
                    $user,
                    'Room ' . ucfirst($status),
                    "Room {$room->room_number} has been {$status}.",
                    $isActive ? 'success' : 'warning',
                    'room',
                    '/admin/rooms/' . $room->id,
                    auth()->id()
                );
            }
        }
    }

    public function deleted(Room $room): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Room Deleted',
                "Room {$room->room_number} has been deleted from the system.",
                'danger',
                'room',
                null,
                auth()->id()
            );
        }
    }

    private function getStatusNotificationType(string $status): string
    {
        return match ($status) {
            'available' => 'success',
            'occupied' => 'info',
            'maintenance' => 'warning',
            'inactive' => 'danger',
            default => 'info',
        };
    }
}
