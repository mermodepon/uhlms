<?php

namespace App\Observers;

use App\Models\Amenity;
use App\Notifications\NotificationHelper;

class AmenityObserver
{
    public function created(Amenity $amenity): void
    {
        NotificationHelper::notifyAllStaff(
            'New Amenity Added',
            "Amenity \"{$amenity->name}\" has been added to the system.",
            'success',
            'amenity',
            '/admin/amenities/'.$amenity->id,
            auth()->id()
        );
    }

    public function updated(Amenity $amenity): void
    {
        $changes = $amenity->getChanges();

        if (array_key_exists('is_active', $changes)) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';

            NotificationHelper::notifyAllStaff(
                'Amenity '.ucfirst($status),
                "Amenity \"{$amenity->name}\" has been {$status}.",
                $changes['is_active'] ? 'success' : 'warning',
                'amenity',
                '/admin/amenities/'.$amenity->id,
                auth()->id()
            );
        } elseif (count(array_diff_key($changes, ['updated_at' => null])) > 0) {
            NotificationHelper::notifyAllStaff(
                'Amenity Updated',
                "Amenity \"{$amenity->name}\" details have been updated.",
                'info',
                'amenity',
                '/admin/amenities/'.$amenity->id,
                auth()->id()
            );
        }
    }

    public function deleted(Amenity $amenity): void
    {
        NotificationHelper::notifyAllStaff(
            'Amenity Deleted',
            "Amenity \"{$amenity->name}\" has been deleted from the system.",
            'danger',
            'amenity',
            null,
            auth()->id()
        );
    }
}
