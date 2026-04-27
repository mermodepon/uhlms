<?php

namespace App\Observers;

use App\Models\Room;
use App\Notifications\NotificationHelper;

class RoomObserver
{
    public function created(Room $room): void
    {
        NotificationHelper::notifyAllStaff(
            'New Room Created',
            "Room {$room->room_number} ({$room->roomType->name}) has been added to the system.",
            'success',
            'room',
            route('filament.admin.resources.rooms.index', [], false).'?tableSearch='.urlencode($room->room_number),
            auth()->id(),
            'rooms_view'
        );
    }

    public function updated(Room $room): void
    {
        $changes = $room->getChanges();

        if (array_key_exists('status', $changes)) {
            $newStatus = $changes['status'];
            $oldStatus = $room->getOriginal('status');

            NotificationHelper::notifyAllStaff(
                'Room Status Changed',
                "Room {$room->room_number} status changed from {$oldStatus} to {$newStatus}.",
                $this->getStatusNotificationType($newStatus),
                'room',
                route('filament.admin.resources.rooms.index', [], false).'?tableSearch='.urlencode($room->room_number),
                auth()->id(),
                'rooms_view'
            );
        }

        if (array_key_exists('is_active', $changes)) {
            $isActive = $changes['is_active'];
            $status = $isActive ? 'activated' : 'deactivated';

            NotificationHelper::notifyAllStaff(
                'Room '.ucfirst($status),
                "Room {$room->room_number} has been {$status}.",
                $isActive ? 'success' : 'warning',
                'room',
                route('filament.admin.resources.rooms.index', [], false).'?tableSearch='.urlencode($room->room_number),
                auth()->id(),
                'rooms_view'
            );
        }
    }

    public function deleted(Room $room): void
    {
        NotificationHelper::notifyAllStaff(
            'Room Deleted',
            "Room {$room->room_number} has been deleted from the system.",
            'danger',
            'room',
            null,
            auth()->id(),
            'rooms_view'
        );
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
