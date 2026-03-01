<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Models\User;

class NotificationHelper
{
    /**
     * Create a notification for all staff members
     */
    public static function notifyAllStaff(
        string $title,
        string $message,
        string $type = 'info',
        string $category = null,
        string $actionUrl = null
    ): void
    {
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
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

    /**
     * Create a notification for a specific user
     */
    public static function notifyUser(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        string $category = null,
        string $actionUrl = null
    ): NotificationModel
    {
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
        string $category = null,
        string $actionUrl = null
    ): void
    {
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
