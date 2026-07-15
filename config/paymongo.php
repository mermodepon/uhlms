<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayMongo API Keys
    |--------------------------------------------------------------------------
    |
    | Your PayMongo public and secret keys. Get these from your PayMongo
    | dashboard: https://dashboard.paymongo.com/developers
    |
    | IMPORTANT: Use test keys (sk_test_* / pk_test_*) for development and
    | live keys (sk_live_* / pk_live_*) only in production.
    |
    */

    'public_key' => env('PAYMONGO_PUBLIC_KEY'),
    'secret_key' => env('PAYMONGO_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Signature Secret
    |--------------------------------------------------------------------------
    |
    | Secret used to verify webhook signatures from PayMongo. Get this from
    | your PayMongo webhook settings after registering your webhook URL.
    |
    */

    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | PayMongo API endpoint. Should not need to change this.
    |
    */

    'api_base_url' => env('PAYMONGO_API_URL', 'https://api.paymongo.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    */

    // Payment intent expiry in hours (default: 24 hours)
    'payment_expiry_hours' => env('PAYMONGO_PAYMENT_EXPIRY_HOURS', 24),

    // Minimum deposit percentage (default: 50% of total)
    'min_deposit_percentage' => env('PAYMONGO_MIN_DEPOSIT_PERCENTAGE', 50),

    // Supported payment methods
    'payment_methods' => [
        'gcash',
        'paymaya',
        'card',
        'grab_pay',
    ],

    // Hosted checkout methods exposed through PayMongo Checkout Sessions.
    'checkout_payment_methods' => [
        'card',
        'gcash',
        'paymaya',
        'grab_pay',
        'billease',
        'qrph',
    ],

    // Enable strict webhook signature verification (recommended: true)
    'strict_webhook_verification' => env('PAYMONGO_STRICT_WEBHOOK_VERIFICATION', true),

    // Reject signed webhook deliveries outside this clock-skew window.
    'webhook_tolerance_seconds' => max(0, (int) env('PAYMONGO_WEBHOOK_TOLERANCE_SECONDS', 300)),

    // Optional CA bundle path for Windows/PHP runtimes without curl.cainfo configured.
    'ca_bundle' => env('PAYMONGO_CA_BUNDLE'),

    // Local-only escape hatch for development machines with broken CA bundles.
    // Keep false for Cloudflare/public runtime targets.
    'allow_insecure_tls' => env('PAYMONGO_ALLOW_INSECURE_TLS', false),
];
