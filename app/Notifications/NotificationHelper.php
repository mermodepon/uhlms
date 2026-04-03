<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\FilamentDatabaseNotification;
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
                ->select('id', 'name', 'email', 'role', 'permissions')
                ->get();
        });
    }

    /**
     * Send a Filament database notification to a user.
     */
    protected static function sendFilamentNotification(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $actionUrl = null
    ): void {
        $user->notify(new FilamentDatabaseNotification(
            title: $title,
            body: $message,
            type: $type,
            actionUrl: $actionUrl,
        ));
    }

    /**
     * Create a notification for all staff members.
     * When $requiredPermission is provided, only users who have that
     * permission will receive the notification (super_admin always passes).
     */
    public static function notifyAllStaff(
        string $title,
        string $message,
        string $type = 'info',
        ?string $category = null,
        ?string $actionUrl = null,
        ?int $createdBy = null,
        ?string $requiredPermission = null
    ): void {
        $staff = self::getStaffUsers();
        foreach ($staff as $user) {
            if ($requiredPermission && ! $user->hasPermission($requiredPermission)) {
                continue;
            }
            self::sendFilamentNotification($user, $title, $message, $type, $actionUrl);
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
    ): void {
        self::sendFilamentNotification($user, $title, $message, $type, $actionUrl);
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
            self::sendFilamentNotification($user, $title, $message, $type, $actionUrl);
        }
    }
}
