<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhook;
use App\Mail\GuestAccountInvitationMail;
use App\Models\PaymentWebhookEvent;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaymentWebhookJobLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_permanently_invalid_payload_marks_receipt_failed_without_retrying(): void
    {
        $receipt = $this->receipt('evt_invalid_payload');
        $job = new ProcessPaymentWebhook($this->payload('payment.paid', ['attributes' => []]), $receipt->id);

        $job->handle();

        $receipt->refresh();
        $this->assertSame(PaymentWebhookEvent::STATUS_FAILED, $receipt->status);
        $this->assertSame(1, $receipt->attempts);
        $this->assertNotNull($receipt->failed_at);
        $this->assertStringContainsString('payment ID or reservation ID', $receipt->last_error);
    }

    public function test_unhandled_legacy_job_event_marks_receipt_ignored(): void
    {
        $receipt = $this->receipt('evt_job_ignored');
        $job = new ProcessPaymentWebhook($this->payload('payment.failed', ['id' => 'pay_failed']), $receipt->id);

        $job->handle();

        $receipt->refresh();
        $this->assertSame(PaymentWebhookEvent::STATUS_IGNORED, $receipt->status);
        $this->assertSame(1, $receipt->attempts);
        $this->assertNotNull($receipt->processed_at);
    }

    public function test_duplicate_job_does_not_reprocess_an_already_processed_receipt(): void
    {
        $receipt = $this->receipt('evt_already_processed');
        $receipt->update([
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'attempts' => 1,
            'processed_at' => now(),
        ]);

        $job = new ProcessPaymentWebhook($this->payload('payment.paid', ['attributes' => []]), $receipt->id);
        $job->handle();

        $receipt->refresh();
        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $receipt->status);
        $this->assertSame(1, $receipt->attempts);
    }

    public function test_retryable_exception_tracks_retrying_then_permanent_failure(): void
    {
        $receipt = $this->receipt('evt_retryable', 'source.chargeable');
        $exception = new RuntimeException(str_repeat('Gateway unavailable ', 150));
        $gateway = Mockery::mock(PaymentGatewayService::class);
        $gateway->shouldReceive('createPaymentFromSource')
            ->once()
            ->with('src_retryable', 10000)
            ->andThrow($exception);
        $this->app->instance(PaymentGatewayService::class, $gateway);

        $job = new ProcessPaymentWebhook($this->payload('source.chargeable', [
            'id' => 'src_retryable',
            'attributes' => ['amount' => 10000],
        ]), $receipt->id);

        try {
            $job->handle();
            $this->fail('The simulated gateway exception was not thrown.');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $receipt->refresh();
        $this->assertSame(PaymentWebhookEvent::STATUS_RETRYING, $receipt->status);
        $this->assertSame(1, $receipt->attempts);
        $this->assertSame('Webhook processing failed (RuntimeException).', $receipt->last_error);
        $this->assertStringNotContainsString('Gateway unavailable', $receipt->last_error);

        $job->failed($exception);

        $receipt->refresh();
        $this->assertSame(PaymentWebhookEvent::STATUS_FAILED, $receipt->status);
        $this->assertNotNull($receipt->failed_at);
        $this->assertSame('Webhook processing failed (RuntimeException).', $receipt->last_error);
    }

    public function test_job_without_receipt_id_remains_compatible_with_predeployment_queue_entries(): void
    {
        $job = new ProcessPaymentWebhook($this->payload('payment.paid', ['attributes' => []]));

        $job->handle();

        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_unregistered_checkin_balance_payer_receives_optional_account_invitation(): void
    {
        Mail::fake();
        $roomType = RoomType::create([
            'name' => 'Webhook Room',
            'base_rate' => 960,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $reservation = Reservation::create([
            'guest_first_name' => 'Unregistered',
            'guest_last_name' => 'Payer',
            'guest_email' => 'unregistered-payer@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'confirmed',
        ]);

        $job = new ProcessPaymentWebhook($this->payload('payment.paid', [
            'id' => 'pay_checkin_balance',
            'attributes' => [
                'amount' => 96000,
                'status' => 'paid',
                'source' => [
                    'id' => 'src_checkin_balance',
                    'attributes' => [
                        'type' => 'gcash',
                        'metadata' => [
                            'reservation_id' => (string) $reservation->id,
                            'payment_type' => 'checkin_balance',
                        ],
                    ],
                ],
            ],
        ]));

        $job->handle();

        $this->assertDatabaseHas('reservation_payments', [
            'reservation_id' => $reservation->id,
            'gateway_status' => 'paid',
            'payment_mode' => 'PayMongo Online',
        ]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'guest_account_id' => null]);
        Mail::assertSent(GuestAccountInvitationMail::class, function (GuestAccountInvitationMail $mail) use ($reservation): bool {
            return $mail->hasTo('unregistered-payer@example.com')
                && $mail->reservation->is($reservation);
        });
    }

    private function receipt(string $eventId, string $eventType = 'payment.paid'): PaymentWebhookEvent
    {
        return PaymentWebhookEvent::create([
            'gateway' => 'paymongo',
            'event_id' => $eventId,
            'event_type' => $eventType,
            'livemode' => false,
            'payload_sha256' => hash('sha256', $eventId),
            'signature_timestamp' => now()->timestamp,
            'status' => PaymentWebhookEvent::STATUS_QUEUED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @return array<string, mixed>
     */
    private function payload(string $eventType, array $eventData): array
    {
        return [
            'data' => [
                'id' => 'evt_job_payload',
                'type' => 'event',
                'attributes' => [
                    'type' => $eventType,
                    'livemode' => false,
                    'data' => $eventData,
                ],
            ],
        ];
    }
}
