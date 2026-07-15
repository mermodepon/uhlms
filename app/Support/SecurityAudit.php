<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SecurityAudit
{
    private const ALLOWED_METADATA = [
        'actor_id', 'target_user_id', 'filename', 'event_type', 'status',
        'route', 'method', 'count', 'window_seconds', 'reason', 'source_hash',
    ];

    /**
     * Write the authoritative local record. Logging failures are deliberately
     * allowed to propagate so callers can fail closed.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $event, array $metadata = []): void
    {
        Log::channel('security')->info($event, $this->sanitize($metadata));
    }

    /**
     * Record first, then notify every super administrator. Delivery failures
     * never erase or invalidate the local audit record.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function alert(
        string $event,
        string $title,
        string $body,
        array $metadata = [],
        string $level = 'warning',
        bool $immediate = false,
    ): void {
        $safe = $this->sanitize($metadata);
        $this->record($event, $safe);

        try {
            $users = User::query()->where('role', 'super_admin')->get();
            $notification = new SecurityAlertNotification($title, $body, $safe, $level);

            if ($immediate) {
                Notification::sendNow($users, $notification);
            } else {
                Notification::send($users, $notification);
            }
        } catch (Throwable $exception) {
            Log::channel('security')->warning('security_notification_delivery_failed', [
                'event_type' => $event,
                'reason' => class_basename($exception),
            ]);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function sanitize(array $metadata): array
    {
        $safe = [];

        foreach (self::ALLOWED_METADATA as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];
            $safe[$key] = is_scalar($value) || $value === null
                ? mb_substr((string) ($value ?? ''), 0, 500)
                : '[redacted]';
        }

        return $safe;
    }
}
