<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentWebhookEvent;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paymongo.webhook_secret' => self::WEBHOOK_SECRET,
            'paymongo.strict_webhook_verification' => true,
            'paymongo.webhook_tolerance_seconds' => 300,
        ]);

        Carbon::setTestNow('2026-07-13 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_paymongo_webhook_rejects_missing_signature(): void
    {
        Bus::fake();

        $response = $this->postJson(route('webhook.paymongo'), $this->payload());

        $response->assertUnauthorized();
        Bus::assertNotDispatched(ProcessPaymentWebhook::class);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_paymongo_webhook_rejects_invalid_signature(): void
    {
        Bus::fake();

        $response = $this->withHeader('PayMongo-Signature', 'invalid-signature')
            ->postJson(route('webhook.paymongo'), $this->payload());

        $response->assertUnauthorized();
        Bus::assertNotDispatched(ProcessPaymentWebhook::class);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_invalid_signature_warning_never_logs_the_signature_or_ip_address(): void
    {
        Bus::fake();
        Log::spy();
        $sourceKey = hash_hmac('sha256', '127.0.0.1', (string) config('app.key'));
        RateLimiter::clear('paymongo-invalid-signature:'.$sourceKey);

        $signature = 't=123,v1=do-not-log-this-signature';
        $this->withHeader('PayMongo-Signature', $signature)
            ->postJson(route('webhook.paymongo'), $this->payload())
            ->assertUnauthorized();

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Invalid PayMongo webhook signature rejected');
    }

    public function test_paymongo_webhook_accepts_test_and_live_signatures_inside_the_window(): void
    {
        Bus::fake();

        $this->sendSigned($this->payload('evt_test_signature'), component: 'te')->assertOk();
        $this->sendSigned($this->payload('evt_live_signature'), component: 'v1')->assertOk();

        Bus::assertDispatchedTimes(ProcessPaymentWebhook::class, 2);
        $this->assertDatabaseCount('payment_webhook_events', 2);
    }

    public function test_dispatched_job_is_encrypted_and_drops_unneeded_sensitive_fields(): void
    {
        Bus::fake();
        $payload = $this->payload('evt_minimal_job');
        $payload['data']['attributes']['data']['attributes']['billing'] = [
            'email' => 'queue-sensitive@example.com',
            'phone' => '09170000000',
        ];
        $payload['data']['attributes']['data']['attributes']['card'] = [
            'number' => '4111111111111111',
            'cvc' => '123',
        ];
        $payload['data']['attributes']['data']['attributes']['metadata']['guest_email'] = 'queue-sensitive@example.com';

        $this->sendSigned($payload)->assertOk();

        Bus::assertDispatched(ProcessPaymentWebhook::class, function (ProcessPaymentWebhook $job): bool {
            $serialized = serialize($job);

            $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
            $this->assertStringContainsString('evt_minimal_job', $serialized);
            $this->assertStringNotContainsString('queue-sensitive@example.com', $serialized);
            $this->assertStringNotContainsString('09170000000', $serialized);
            $this->assertStringNotContainsString('4111111111111111', $serialized);
            $this->assertStringNotContainsString('payment_data', $serialized);
            $this->assertStringNotContainsString('source_data', $serialized);

            return true;
        });
    }

    public function test_database_queue_record_encrypts_the_normalized_job_command(): void
    {
        config(['queue.default' => 'database']);
        $payload = $this->payload('evt_encrypted_database_job');
        $payload['data']['attributes']['data']['attributes']['metadata']['guest_email'] = 'database-queue@example.com';

        dispatch(new ProcessPaymentWebhook($payload));

        $queuedPayload = (string) DB::table('jobs')->value('payload');

        $this->assertNotSame('', $queuedPayload);
        $this->assertStringNotContainsString('evt_encrypted_database_job', $queuedPayload);
        $this->assertStringNotContainsString('database-queue@example.com', $queuedPayload);
        $this->assertStringNotContainsString('reservation_id', $queuedPayload);
    }

    public function test_signature_window_accepts_the_exact_past_and_future_boundaries(): void
    {
        Bus::fake();

        $this->sendSigned(
            $this->payload('evt_past_boundary'),
            now()->subSeconds(300)->timestamp,
        )->assertOk();
        $this->sendSigned(
            $this->payload('evt_future_boundary'),
            now()->addSeconds(300)->timestamp,
        )->assertOk();

        Bus::assertDispatchedTimes(ProcessPaymentWebhook::class, 2);
    }

    public function test_signature_window_rejects_stale_and_excessively_future_timestamps(): void
    {
        Bus::fake();

        $this->sendSigned(
            $this->payload('evt_too_old'),
            now()->subSeconds(301)->timestamp,
        )->assertUnauthorized();
        $this->sendSigned(
            $this->payload('evt_too_far_future'),
            now()->addSeconds(301)->timestamp,
        )->assertUnauthorized();

        Bus::assertNotDispatched(ProcessPaymentWebhook::class);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_signature_verification_rejects_missing_and_malformed_timestamps(): void
    {
        Bus::fake();
        $payload = $this->payload();
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $missingTimestamp = hash_hmac('sha256', now()->timestamp.'.'.$json, self::WEBHOOK_SECRET);
        $this->sendRaw($json, "te={$missingTimestamp}")->assertUnauthorized();

        $malformedTimestamp = 'not-a-timestamp';
        $malformedHmac = hash_hmac('sha256', $malformedTimestamp.'.'.$json, self::WEBHOOK_SECRET);
        $this->sendRaw($json, "t={$malformedTimestamp},te={$malformedHmac}")->assertUnauthorized();

        Bus::assertNotDispatched(ProcessPaymentWebhook::class);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_duplicate_event_is_acknowledged_without_another_job_or_receipt(): void
    {
        Bus::fake();
        $payload = $this->payload('evt_duplicate');

        $this->sendSigned($payload)->assertOk();
        $this->sendSigned($payload)->assertOk();

        Bus::assertDispatchedTimes(ProcessPaymentWebhook::class, 1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseHas('payment_webhook_events', [
            'gateway' => 'paymongo',
            'event_id' => 'evt_duplicate',
            'event_type' => 'payment.paid',
            'status' => PaymentWebhookEvent::STATUS_QUEUED,
            'signature_timestamp' => now()->timestamp,
        ]);
    }

    public function test_reused_event_id_with_different_payload_is_logged_and_not_dispatched(): void
    {
        Bus::fake();
        Log::spy();

        $first = $this->payload('evt_collision');
        $second = $this->payload('evt_collision');
        $second['data']['attributes']['data']['attributes']['amount'] = 20000;

        $this->sendSigned($first)->assertOk();
        $this->sendSigned($second)->assertOk();

        Bus::assertDispatchedTimes(ProcessPaymentWebhook::class, 1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'PayMongo webhook event ID was reused with a different payload',
                Mockery::on(fn (array $context): bool => $context['event_id'] === 'evt_collision'),
            );
    }

    public function test_unhandled_valid_event_is_recorded_once_as_ignored(): void
    {
        Bus::fake();
        $payload = $this->payload('evt_unhandled', 'payment.failed');

        $this->sendSigned($payload)->assertOk();
        $this->sendSigned($payload)->assertOk();

        Bus::assertNotDispatched(ProcessPaymentWebhook::class);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_unhandled',
            'status' => PaymentWebhookEvent::STATUS_IGNORED,
        ]);
        $this->assertNotNull(PaymentWebhookEvent::firstOrFail()->processed_at);
    }

    public function test_signed_malformed_event_envelope_is_rejected_before_persistence(): void
    {
        Bus::fake();

        $missingId = $this->payload();
        unset($missingId['data']['id']);
        $this->sendSigned($missingId)->assertBadRequest();

        $wrongResourceType = $this->payload();
        $wrongResourceType['data']['type'] = 'payment';
        $this->sendSigned($wrongResourceType)->assertBadRequest();

        Bus::assertNotDispatched(ProcessPaymentWebhook::class);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_dispatch_failure_rolls_back_the_event_receipt(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('Queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        $this->withoutExceptionHandling();

        try {
            $this->sendSigned($this->payload('evt_dispatch_failure'));
            $this->fail('The simulated dispatch failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Queue unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        string $eventId = 'evt_test_123',
        string $eventType = 'payment.paid',
    ): array {
        return [
            'data' => [
                'id' => $eventId,
                'type' => 'event',
                'attributes' => [
                    'type' => $eventType,
                    'livemode' => false,
                    'data' => [
                        'id' => 'pay_test_123',
                        'attributes' => [
                            'amount' => 10000,
                            'status' => 'paid',
                            'metadata' => [
                                'reservation_id' => '1',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function sendSigned(
        array $payload,
        ?int $timestamp = null,
        string $component = 'te',
    ) {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= now()->timestamp;
        $hmac = hash_hmac('sha256', $timestamp.'.'.$json, self::WEBHOOK_SECRET);

        return $this->sendRaw($json, "t={$timestamp},{$component}={$hmac}");
    }

    private function sendRaw(string $json, string $signatureHeader)
    {
        return $this->withHeader('PayMongo-Signature', $signatureHeader)
            ->call(
                method: 'POST',
                uri: route('webhook.paymongo'),
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_PAYMONGO_SIGNATURE' => $signatureHeader,
                ],
                content: $json,
            );
    }
}
