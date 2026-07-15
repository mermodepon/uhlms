<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

class SecurityMonitor
{
    public function response(int $status, ?string $route, string $method, ?string $ip): void
    {
        if (! in_array($status, [401, 403, 419], true)) {
            return;
        }

        $this->count(
            category: 'http',
            fingerprint: implode('|', [$status, $route ?: 'unnamed', strtoupper($method), $this->sourceHash($ip)]),
            threshold: (int) config('security.monitoring.response_threshold', 10),
            window: (int) config('security.monitoring.response_window_seconds', 300),
            title: 'Repeated HTTP security failures',
            metadata: ['status' => $status, 'route' => $route ?: 'unnamed', 'method' => strtoupper($method), 'source_hash' => $this->sourceHash($ip)],
        );
    }

    public function webhook(string $event): void
    {
        $this->count(
            category: 'webhook',
            fingerprint: $event,
            threshold: (int) config('security.monitoring.webhook_threshold', 5),
            window: (int) config('security.monitoring.webhook_window_seconds', 300),
            title: 'Repeated PayMongo webhook failures',
            metadata: ['event_type' => $event],
        );
    }

    public function adminAuthentication(string $event, ?string $ip): void
    {
        $this->count(
            category: 'admin-auth',
            fingerprint: $event.'|'.$this->sourceHash($ip),
            threshold: (int) config('security.monitoring.admin_auth_threshold', 5),
            window: (int) config('security.monitoring.admin_auth_window_seconds', 600),
            title: 'Repeated administrator authentication failures',
            metadata: ['event_type' => $event, 'source_hash' => $this->sourceHash($ip)],
        );
    }

    /** @param array<string, scalar|null> $metadata */
    private function count(string $category, string $fingerprint, int $threshold, int $window, string $title, array $metadata): void
    {
        if (! config('security.monitoring.enabled', true)) {
            return;
        }

        try {
            $cache = Cache::store('file');
            $digest = hash_hmac('sha256', $fingerprint, (string) config('app.key'));
            $bucket = intdiv(now()->timestamp, $window);
            $counterKey = "security-monitor:{$category}:{$digest}:{$bucket}";

            $cache->add($counterKey, 0, $window * 2);
            $count = (int) $cache->increment($counterKey);
            if ($count < $threshold) {
                return;
            }

            $cooldownKey = "security-monitor:cooldown:{$category}:{$digest}";
            if (! $cache->add($cooldownKey, true, (int) config('security.monitoring.alert_cooldown_seconds', 1800))) {
                return;
            }

            $hourKey = 'security-monitor:alerts:'.now()->format('YmdH');
            $cache->add($hourKey, 0, 7200);
            if ((int) $cache->increment($hourKey) > (int) config('security.monitoring.hourly_alert_cap', 20)) {
                return;
            }

            app(SecurityAudit::class)->alert(
                'security_threshold_reached',
                $title,
                "{$count} matching events occurred within {$window} seconds.",
                array_merge($metadata, ['count' => $count, 'window_seconds' => $window]),
            );
        } catch (Throwable) {
            // Monitoring is observational and must never alter the original response.
        }
    }

    private function sourceHash(?string $ip): string
    {
        return substr(hash_hmac('sha256', (string) ($ip ?: 'unknown'), (string) config('app.key')), 0, 24);
    }
}
