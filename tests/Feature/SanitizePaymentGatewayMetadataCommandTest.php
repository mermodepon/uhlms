<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanitizePaymentGatewayMetadataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_without_writing_and_force_cleanup_is_idempotent(): void
    {
        $reservation = $this->reservation();
        $paid = $this->payment($reservation, 'paid', [
            'webhook_received_at' => now()->toIso8601String(),
            'payment_intent_id' => 'pi_safe',
            'payment_data' => ['attributes' => ['billing' => ['email' => 'guest@example.com']]],
            'source_data' => ['attributes' => ['card_number' => '4111111111111111']],
            'unknown_key' => 'remove-me',
        ], 'pay_raw');
        $cancelled = $this->payment($reservation, 'cancelled', [
            'checkout_url' => 'https://checkout.paymongo.com/cancelled-link',
            'cancelled_by' => 42,
        ], 'pay_cancelled');
        $pending = $this->payment($reservation, 'pending', [
            'checkout_url' => 'https://checkout.paymongo.com/pending-link',
            'payment_type' => 'checkin_balance',
        ], 'pay_pending');

        $this->artisan('payments:sanitize-gateway-metadata', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertArrayHasKey('payment_data', $paid->fresh()->gateway_metadata);
        $this->assertArrayHasKey('checkout_url', $cancelled->fresh()->gateway_metadata);

        $this->artisan('payments:sanitize-gateway-metadata', ['--force' => true])
            ->assertSuccessful();

        $paidMetadata = $paid->fresh()->gateway_metadata;
        $this->assertArrayNotHasKey('payment_data', $paidMetadata);
        $this->assertArrayNotHasKey('source_data', $paidMetadata);
        $this->assertArrayNotHasKey('unknown_key', $paidMetadata);
        $this->assertSame('pi_safe', $paidMetadata['payment_intent_id']);
        $this->assertArrayNotHasKey('checkout_url', $cancelled->fresh()->gateway_metadata);
        $this->assertSame(
            'https://checkout.paymongo.com/pending-link',
            $pending->fresh()->gateway_metadata['checkout_url'],
        );

        $afterFirstRun = ReservationPayment::query()
            ->orderBy('id')
            ->pluck('gateway_metadata', 'id')
            ->all();

        $this->artisan('payments:sanitize-gateway-metadata', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(
            $afterFirstRun,
            ReservationPayment::query()->orderBy('id')->pluck('gateway_metadata', 'id')->all(),
        );
    }

    public function test_command_requires_an_explicit_mode(): void
    {
        $this->artisan('payments:sanitize-gateway-metadata')->assertExitCode(2);
        $this->artisan('payments:sanitize-gateway-metadata', [
            '--dry-run' => true,
            '--force' => true,
        ])->assertExitCode(2);
    }

    private function reservation(): Reservation
    {
        return Reservation::create([
            'reference_number' => '2026-RETENTION-1',
            'guest_name' => 'Retention Guest',
            'guest_first_name' => 'Retention',
            'guest_last_name' => 'Guest',
            'guest_email' => 'retention@example.com',
            'guest_phone' => '09171234567',
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'approved',
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function payment(
        Reservation $reservation,
        string $gatewayStatus,
        array $metadata,
        string $paymentId,
    ): ReservationPayment {
        return ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 1000,
            'payment_mode' => 'PayMongo Online',
            'gateway' => 'paymongo',
            'gateway_payment_id' => $paymentId,
            'gateway_status' => $gatewayStatus,
            'gateway_metadata' => $metadata,
            'status' => $gatewayStatus === 'paid' ? 'posted' : $gatewayStatus,
            'received_at' => now(),
        ]);
    }
}
