<?php

namespace App\Support;

class PayMongoWebhookData
{
    /**
     * Convert a verified PayMongo event into the minimal structure required by the queue job.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function forQueue(array $payload): array
    {
        $normalized = array_key_exists('event_type', $payload)
            ? $payload
            : [
                'event_id' => data_get($payload, 'data.id'),
                'event_type' => data_get($payload, 'data.attributes.type'),
                'livemode' => data_get($payload, 'data.attributes.livemode'),
                'resource' => data_get($payload, 'data.attributes.data'),
            ];

        $eventType = is_string($normalized['event_type'] ?? null)
            ? $normalized['event_type']
            : null;
        $resource = is_array($normalized['resource'] ?? null)
            ? $normalized['resource']
            : [];

        return [
            'event_id' => is_string($normalized['event_id'] ?? null)
                ? $normalized['event_id']
                : null,
            'event_type' => $eventType,
            'livemode' => is_bool($normalized['livemode'] ?? null)
                ? $normalized['livemode']
                : null,
            'resource' => match ($eventType) {
                'source.chargeable' => self::source($resource),
                'payment.paid' => self::payment($resource),
                'checkout_session.payment.paid' => self::checkoutSession($resource),
                default => self::unknownResource($resource),
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    public static function payment(array $payment): array
    {
        $attributes = is_array($payment['attributes'] ?? null)
            ? $payment['attributes']
            : $payment;
        $source = is_array($attributes['source'] ?? null)
            ? $attributes['source']
            : [];
        $sourceAttributes = is_array($source['attributes'] ?? null)
            ? $source['attributes']
            : $source;

        $metadata = self::metadata($sourceAttributes['metadata'] ?? null);
        if ($metadata === []) {
            $metadata = self::metadata($source['metadata'] ?? null);
        }
        if ($metadata === []) {
            $metadata = self::metadata($attributes['metadata'] ?? null);
        }

        return [
            'id' => self::stringOrNull($payment['id'] ?? null),
            'status' => self::stringOrNull($attributes['status'] ?? null),
            'amount' => self::integerOrNull($attributes['amount'] ?? null),
            'payment_intent_id' => self::stringOrNull($attributes['payment_intent_id'] ?? null),
            'checkout_session_id' => self::stringOrNull($attributes['checkout_session_id'] ?? null),
            'source' => [
                'id' => self::stringOrNull($source['id'] ?? null),
                'type' => self::stringOrNull($sourceAttributes['type'] ?? $source['type'] ?? null),
            ],
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private static function source(array $source): array
    {
        $attributes = is_array($source['attributes'] ?? null)
            ? $source['attributes']
            : $source;

        return [
            'id' => self::stringOrNull($source['id'] ?? null),
            'amount' => self::integerOrNull($attributes['amount'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $checkoutSession
     * @return array<string, mixed>
     */
    private static function checkoutSession(array $checkoutSession): array
    {
        $attributes = is_array($checkoutSession['attributes'] ?? null)
            ? $checkoutSession['attributes']
            : $checkoutSession;
        $payments = is_array($attributes['payments'] ?? null)
            ? $attributes['payments']
            : [];
        $payment = is_array($attributes['payment'] ?? null)
            ? $attributes['payment']
            : ($payments[0] ?? []);

        return [
            'id' => self::stringOrNull($checkoutSession['id'] ?? null),
            'metadata' => self::metadata($attributes['metadata'] ?? null),
            'payment' => is_array($payment) ? self::payment($payment) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    private static function unknownResource(array $resource): array
    {
        return [
            'id' => self::stringOrNull($resource['id'] ?? null),
        ];
    }

    /**
     * @return array{reservation_id?:string,payment_type?:string}
     */
    private static function metadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $normalized = [];

        foreach (['reservation_id', 'payment_type'] as $key) {
            if (is_scalar($metadata[$key] ?? null)) {
                $normalized[$key] = (string) $metadata[$key];
            }
        }

        return $normalized;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private static function integerOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/\A\d+\z/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
