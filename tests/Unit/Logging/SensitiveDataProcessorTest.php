<?php

namespace Tests\Unit\Logging;

use App\Logging\SensitiveDataProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class SensitiveDataProcessorTest extends TestCase
{
    public function test_it_recursively_redacts_sensitive_context_and_truncates_oversized_values(): void
    {
        $processor = new SensitiveDataProcessor;
        $record = new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'testing',
            level: Level::Warning,
            message: 'Sensitive context test',
            context: [
                'event_id' => 'evt_safe_123',
                'signature' => 't=123,v1=secret-signature',
                'nested' => [
                    'guest_email' => 'guest@example.com',
                    'guest_name' => 'Sensitive Guest',
                    'amount' => 1250,
                    'ip_address' => '203.0.113.10',
                    'payment_data' => ['card_number' => '4111111111111111'],
                    'status' => 'rejected',
                ],
                'long_value' => str_repeat('x', 3000),
            ],
            extra: [
                'authorization' => 'Bearer secret-token',
            ],
        );

        $sanitized = $processor($record);

        $this->assertSame('evt_safe_123', $sanitized->context['event_id']);
        $this->assertSame('[REDACTED]', $sanitized->context['signature']);
        $this->assertSame('[REDACTED]', $sanitized->context['nested']['guest_email']);
        $this->assertSame('[REDACTED]', $sanitized->context['nested']['guest_name']);
        $this->assertSame('[REDACTED]', $sanitized->context['nested']['amount']);
        $this->assertSame('[REDACTED]', $sanitized->context['nested']['ip_address']);
        $this->assertSame('[REDACTED]', $sanitized->context['nested']['payment_data']);
        $this->assertSame('rejected', $sanitized->context['nested']['status']);
        $this->assertStringEndsWith('[TRUNCATED]', $sanitized->context['long_value']);
        $this->assertLessThan(2100, strlen($sanitized->context['long_value']));
        $this->assertSame('[REDACTED]', $sanitized->extra['authorization']);
    }
}
