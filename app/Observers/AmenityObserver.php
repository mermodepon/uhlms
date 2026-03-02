<?php

namespace App\Observers;

use App\Models\Amenity;
use App\Models\Notification;
use App\Models\User;

class AmenityObserver
{
    public function created(Amenity $amenity): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'New Amenity Added',
                "Amenity \"{$amenity->name}\" has been added to the system.",
                'success',
                'amenity',
                '/admin/amenities/' . $amenity->id,
                auth()->id()
            );
        }
    }

    public function updated(Amenity $amenity): void
    {
        $changes = $amenity->getChanges();

        if (array_key_exists('is_active', $changes)) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';

            $staff = User::whereIn('role', ['admin', 'staff'])->get();
            foreach ($staff as $user) {
                Notification::createNotification(
                    $user,
                    'Amenity ' . ucfirst($status),
                    "Amenity \"{$amenity->name}\" has been {$status}.",
                    $changes['is_active'] ? 'success' : 'warning',
                    'amenity',
                    '/admin/amenities/' . $amenity->id,
                    auth()->id()
                );
            }
        } elseif (count(array_diff_key($changes, ['updated_at' => null])) > 0) {
            $staff = User::whereIn('role', ['admin', 'staff'])->get();
            foreach ($staff as $user) {
                Notification::createNotification(
                    $user,
                    'Amenity Updated',
                    "Amenity \"{$amenity->name}\" details have been updated.",
                    'info',
                    'amenity',
                    '/admin/amenities/' . $amenity->id,
                    auth()->id()
                );
            }
        }
    }

    public function deleted(Amenity $amenity): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Amenity Deleted',
                "Amenity \"{$amenity->name}\" has been deleted from the system.",
                'danger',
                'amenity',
                null,
                auth()->id()
            );
        }
    }
}
