<?php

namespace App\Logging;

use Monolog\LogRecord;

class SensitiveDataProcessor
{
    private const REDACTED = '[REDACTED]';

    private const MAX_STRING_LENGTH = 2048;

    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'signature',
        'paymongo-signature',
        'email',
        'guest_email',
        'guest_name',
        'guest_first_name',
        'guest_last_name',
        'first_name',
        'last_name',
        'full_name',
        'phone',
        'guest_phone',
        'mobile',
        'address',
        'guest_address',
        'ip',
        'ip_address',
        'user_agent',
        'card',
        'card_number',
        'cardholder_name',
        'cvc',
        'cvv',
        'billing',
        'billing_address',
        'amount',
        'payment_amount',
        'total_amount',
        'gateway_payment_id',
        'gateway_source_id',
        'payment_intent_id',
        'checkout_url',
        'client_key',
        'payment_data',
        'source_data',
        'webhook',
        'webhook_data',
        'payload',
        'raw_payload',
        'body',
        'request_body',
        'response_body',
        'request',
        'response',
        'headers',
        'trace',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->sanitizeArray($record->context),
            extra: $this->sanitizeArray($record->extra),
        );
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    public function sanitizeArray(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $sanitized[$key] = self::REDACTED;

                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if (is_string($value) && mb_strlen($value) > self::MAX_STRING_LENGTH) {
            return mb_substr($value, 0, self::MAX_STRING_LENGTH).'[TRUNCATED]';
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return preg_match(
            '/(?:^|_)(?:password|token|secret|signature|email|phone|mobile|card_number|cvc|cvv)$/',
            $normalized,
        ) === 1;
    }
}
