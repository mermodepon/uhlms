<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;

class SettingObserver
{
    public function created(Setting $setting): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Site Setting Added',
                "A new site setting \"{$setting->key}\" has been configured.",
                'info',
                'setting',
                '/admin/site-settings',
                auth()->id()
            );
        }
    }

    public function updated(Setting $setting): void
    {
        $changes = $setting->getChanges();

        if (count(array_diff_key($changes, ['updated_at' => null])) === 0) {
            return;
        }

        $label = $this->getSettingLabel($setting->key);

        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Site Setting Updated',
                "The site setting \"{$label}\" has been updated.",
                'info',
                'setting',
                '/admin/site-settings',
                auth()->id()
            );
        }
    }

    public function deleted(Setting $setting): void
    {
        $label = $this->getSettingLabel($setting->key);

        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Site Setting Deleted',
                "The site setting \"{$label}\" has been removed.",
                'danger',
                'setting',
                '/admin/site-settings',
                auth()->id()
            );
        }
    }

    private function getSettingLabel(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }
}
