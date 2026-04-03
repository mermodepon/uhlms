<?php

namespace App\Observers;

use App\Models\Service;
use App\Notifications\NotificationHelper;

class ServiceObserver
{
    public function created(Service $service): void
    {
        NotificationHelper::notifyAllStaff(
            'New Add-On Created',
            "Add-On \"{$service->name}\" has been added to the system.",
            'success',
            'service',
            url('/admin/services?tableSearch='.urlencode($service->name)),
            auth()->id(),
            'addons_view'
        );
    }

    public function updated(Service $service): void
    {
        $changes = $service->getChanges();

        if (array_key_exists('is_active', $changes)) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';

            NotificationHelper::notifyAllStaff(
                'Add-On '.ucfirst($status),
                "Add-On \"{$service->name}\" has been {$status}.",
                $changes['is_active'] ? 'success' : 'warning',
                'service',
                url('/admin/services?tableSearch='.urlencode($service->name)),
                auth()->id(),
                'addons_view'
            );
        } elseif (count(array_diff_key($changes, ['updated_at' => null])) > 0) {
            NotificationHelper::notifyAllStaff(
                'Add-On Updated',
                "Add-On \"{$service->name}\" details have been updated.",
                'info',
                'service',
                url('/admin/services?tableSearch='.urlencode($service->name)),
                auth()->id(),
                'addons_view'
            );
        }
    }

    public function deleted(Service $service): void
    {
        NotificationHelper::notifyAllStaff(
            'Add-On Deleted',
            "Add-On \"{$service->name}\" has been deleted from the system.",
            'danger',
            'service',
            null,
            auth()->id(),
            'addons_view'
        );
    }
}
