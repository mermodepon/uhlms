<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\RoomType;
use App\Models\User;

class RoomTypeObserver
{
    public function created(RoomType $roomType): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'New Room Type Created',
                "Room Type \"{$roomType->name}\" has been added to the system.",
                'success',
                'room_type',
                '/admin/room-types/' . $roomType->id,
                auth()->id()
            );
        }
    }

    public function updated(RoomType $roomType): void
    {
        $changes = $roomType->getChanges();

        if (array_key_exists('is_active', $changes)) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';

            $staff = User::whereIn('role', ['admin', 'staff'])->get();
            foreach ($staff as $user) {
                Notification::createNotification(
                    $user,
                    'Room Type ' . ucfirst($status),
                    "Room Type \"{$roomType->name}\" has been {$status}.",
                    $changes['is_active'] ? 'success' : 'warning',
                    'room_type',
                    '/admin/room-types/' . $roomType->id,
                    auth()->id()
                );
            }
        } elseif (count(array_diff_key($changes, ['updated_at' => null])) > 0) {
            $staff = User::whereIn('role', ['admin', 'staff'])->get();
            foreach ($staff as $user) {
                Notification::createNotification(
                    $user,
                    'Room Type Updated',
                    "Room Type \"{$roomType->name}\" details have been updated.",
                    'info',
                    'room_type',
                    '/admin/room-types/' . $roomType->id,
                    auth()->id()
                );
            }
        }
    }

    public function deleted(RoomType $roomType): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Room Type Deleted',
                "Room Type \"{$roomType->name}\" has been deleted from the system.",
                'danger',
                'room_type',
                null,
                auth()->id()
            );
        }
    }
}
