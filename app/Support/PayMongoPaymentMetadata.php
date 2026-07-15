<?php

namespace App\Support;

class PayMongoPaymentMetadata
{
    /**
     * @var array<int, string>
     */
    private const SCALAR_KEYS = [
        'checkout_session_created_at',
        'checkout_session_id',
        'payment_type',
        'webhook_received_at',
        'webhook_event_id',
        'payment_intent_id',
        'guest_result_token',
        'paid_after_staff_cancellation',
        'cancelled_at',
        'cancelled_by',
        'cancellation_source',
        'cancellation_reason',
        'reservation_id',
        'reservation_ref',
    ];

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public static function sanitize(?array $metadata, ?string $gatewayStatus = null): array
    {
        $metadata ??= [];
        $sanitized = [];

        foreach (self::SCALAR_KEYS as $key) {
            $value = $metadata[$key] ?? null;

            if (is_scalar($value)) {
                $sanitized[$key] = $value;
            }
        }

        $paymentMethods = $metadata['checkout_payment_methods'] ?? null;
        if (is_array($paymentMethods)) {
            $sanitized['checkout_payment_methods'] = array_values(array_filter(
                $paymentMethods,
                static fn (mixed $method): bool => is_string($method) && $method !== '',
            ));
        }

        $checkoutUrl = $metadata['checkout_url'] ?? null;
        if ($gatewayStatus === 'pending' && is_string($checkoutUrl) && $checkoutUrl !== '') {
            $sanitized['checkout_url'] = $checkoutUrl;
        }

        return $sanitized;
    }
}
