<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NotificationHelper
{
    /**
     * Get cached staff users to avoid repeated queries.
     * Cache expires after 15 minutes.
     */
    protected static function getStaffUsers(): \Illuminate\Support\Collection
    {
        return Cache::remember('system.staff_users', 900, function () {
            return User::whereIn('role', ['super_admin', 'admin', 'staff'])
                ->select('id', 'name', 'email')
                ->get();
        });
    }

    /**
     * Create a notification for all staff members
     */
    public static function notifyAllStaff(
        string $title,
        string $message,
        string $type = 'info',
        ?string $category = null,
        ?string $actionUrl = null,
        ?int $createdBy = null
    ): void {
        $staff = self::getStaffUsers();
        foreach ($staff as $user) {
            NotificationModel::createNotification(
                $user,
                $title,
                $message,
                $type,
                $category,
                $actionUrl,
                $createdBy ?? auth()->id()
            );
        }
    }

    /**
     * Create a notification for a specific user
     */
    public static function notifyUser(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $category = null,
        ?string $actionUrl = null
    ): NotificationModel {
        return NotificationModel::createNotification(
            $user,
            $title,
            $message,
            $type,
            $category,
            $actionUrl
        );
    }

    /**
     * Create a notification for multiple users
     */
    public static function notifyUsers(
        array $userIds,
        string $title,
        string $message,
        string $type = 'info',
        ?string $category = null,
        ?string $actionUrl = null
    ): void {
        $users = User::whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            NotificationModel::createNotification(
                $user,
                $title,
                $message,
                $type,
                $category,
                $actionUrl
            );
        }
    }
}
