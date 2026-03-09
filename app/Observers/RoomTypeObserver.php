<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\RoomType;
use App\Models\User;
use App\Notifications\NotificationHelper;

class RoomTypeObserver
{
    public function created(RoomType $roomType): void
    {
        $sharing = $roomType->room_sharing_type === 'private' ? 'Private (exclusive)' : 'Public (shared)';
        NotificationHelper::notifyAllStaff(
            'New Room Type Created',
            "Room Type \"{$roomType->name}\" ({$sharing}) has been added to the system.",
            'success',
            'room_type',
            '/admin/room-types/' . $roomType->id,
            auth()->id()
        );
    }

    public function updated(RoomType $roomType): void
    {
        $changes = $roomType->getChanges();

        if (array_key_exists('is_active', $changes)) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';

            NotificationHelper::notifyAllStaff(
                'Room Type ' . ucfirst($status),
                "Room Type \"{$roomType->name}\" has been {$status}.",
                $changes['is_active'] ? 'success' : 'warning',
                'room_type',
                '/admin/room-types/' . $roomType->id,
                auth()->id()
            );
        } elseif (array_key_exists('room_sharing_type', $changes)) {
            $old = $roomType->getOriginal('room_sharing_type');
            $new = $changes['room_sharing_type'];
            NotificationHelper::notifyAllStaff(
                'Room Type Sharing Updated',
                "Room Type \"{$roomType->name}\" sharing changed from {$old} to {$new}.",
                'info',
                'room_type',
                '/admin/room-types/' . $roomType->id,
                auth()->id()
            );
        } elseif (count(array_diff_key($changes, ['updated_at' => null])) > 0) {
            NotificationHelper::notifyAllStaff(
                'Room Type Updated',
                "Room Type \"{$roomType->name}\" details have been updated.",
                'info',
                'room_type',
                '/admin/room-types/' . $roomType->id,
                auth()->id()
            );
        }
    }

    public function deleted(RoomType $roomType): void
    {
        NotificationHelper::notifyAllStaff(
            'Room Type Deleted',
            "Room Type \"{$roomType->name}\" has been deleted from the system.",
            'danger',
            'room_type',
            null,
            auth()->id()
        );
    }
}
