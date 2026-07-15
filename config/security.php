<?php

$trustedHosts = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_HOSTS', 'app.uhlms.uk,localhost,127.0.0.1,::1'))
)));

return [
    'trusted_hosts' => $trustedHosts,

    'mfa' => [
        'mode' => strtolower((string) env('ADMIN_MFA_MODE', 'optional')),
        'required_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MFA_REQUIRED_ROLES', 'super_admin,admin'))
        ))),
        'pending_ttl_seconds' => max(60, (int) env('MFA_PENDING_TTL_SECONDS', 600)),
        'recent_seconds' => max(60, (int) env('MFA_RECENT_SECONDS', 600)),
    ],

    'backups' => [
        'directory' => env('BACKUP_DIRECTORY', storage_path('app/backups')),
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
        'routine_keep' => max(1, (int) env('BACKUP_ROUTINE_KEEP', 10)),
        'routine_days' => max(1, (int) env('BACKUP_ROUTINE_DAYS', 30)),
        'pre_restore_keep' => max(1, (int) env('BACKUP_PRE_RESTORE_KEEP', 3)),
        'pre_restore_days' => max(1, (int) env('BACKUP_PRE_RESTORE_DAYS', 7)),
        'excluded_data_tables' => [
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'password_reset_tokens',
        ],
    ],

    'monitoring' => [
        'enabled' => filter_var(env('SECURITY_MONITOR_ENABLED', true), FILTER_VALIDATE_BOOL),
        'response_threshold' => max(1, (int) env('SECURITY_RESPONSE_THRESHOLD', 10)),
        'response_window_seconds' => max(60, (int) env('SECURITY_RESPONSE_WINDOW_SECONDS', 300)),
        'webhook_threshold' => max(1, (int) env('SECURITY_WEBHOOK_THRESHOLD', 5)),
        'webhook_window_seconds' => max(60, (int) env('SECURITY_WEBHOOK_WINDOW_SECONDS', 300)),
        'admin_auth_threshold' => max(1, (int) env('SECURITY_ADMIN_AUTH_THRESHOLD', 5)),
        'admin_auth_window_seconds' => max(60, (int) env('SECURITY_ADMIN_AUTH_WINDOW_SECONDS', 600)),
        'alert_cooldown_seconds' => max(60, (int) env('SECURITY_ALERT_COOLDOWN_SECONDS', 1800)),
        'hourly_alert_cap' => max(1, (int) env('SECURITY_ALERT_HOURLY_CAP', 20)),
    ],

    'browser_headers' => [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-XSS-Protection' => '0',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), hid=(), display-capture=()',
    ],

    'transport' => [
        'enforce_https' => filter_var(env('PUBLIC_HTTPS_ENFORCED', false), FILTER_VALIDATE_BOOL),
        'canonical_host' => strtolower((string) env('PUBLIC_HTTPS_HOST', 'app.uhlms.uk')),
        'hsts_max_age' => max(0, (int) env('HSTS_MAX_AGE', 2592000)),
        'hsts_include_subdomains' => false,
        'hsts_preload' => false,
    ],

    'content_security_policy' => [
        'mode' => strtolower((string) env('CONTENT_SECURITY_POLICY_MODE', 'report-only')),
        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-eval'", 'https://cdn.jsdelivr.net'],
            'script-src-attr' => ["'unsafe-inline'"],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://fonts.googleapis.com', 'https://fonts.bunny.net'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com', 'https://fonts.bunny.net'],
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'media-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'connect-src' => ["'self'", 'https:', 'ws:', 'wss:'],
            'frame-src' => ["'self'", 'https://www.youtube.com', 'https://www.youtube-nocookie.com'],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
        ],
    ],
];
