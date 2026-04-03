<?php

namespace App\Observers;

use App\Models\Floor;
use App\Notifications\NotificationHelper;

class FloorObserver
{
    public function created(Floor $floor): void
    {
        NotificationHelper::notifyAllStaff(
            'New Floor Created',
            "Floor \"{$floor->name}\" (Level {$floor->level}) has been added to the system.",
            'success',
            'floor',
            url('/admin/floors?tableSearch='.urlencode($floor->name)),
            auth()->id(),
            'floors_view'
        );
    }

    public function updated(Floor $floor): void
    {
        $changes = $floor->getChanges();

        if (array_key_exists('is_active', $changes)) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';

            NotificationHelper::notifyAllStaff(
                'Floor '.ucfirst($status),
                "Floor \"{$floor->name}\" has been {$status}.",
                $changes['is_active'] ? 'success' : 'warning',
                'floor',
                url('/admin/floors?tableSearch='.urlencode($floor->name)),
                auth()->id(),
                'floors_view'
            );
        } elseif (count(array_diff_key($changes, ['updated_at' => null])) > 0) {
            NotificationHelper::notifyAllStaff(
                'Floor Updated',
                "Floor \"{$floor->name}\" details have been updated.",
                'info',
                'floor',
                url('/admin/floors?tableSearch='.urlencode($floor->name)),
                auth()->id(),
                'floors_view'
            );
        }
    }

    public function deleted(Floor $floor): void
    {
        NotificationHelper::notifyAllStaff(
            'Floor Deleted',
            "Floor \"{$floor->name}\" has been deleted from the system.",
            'danger',
            'floor',
            null,
            auth()->id(),
            'floors_view'
        );
    }
}
