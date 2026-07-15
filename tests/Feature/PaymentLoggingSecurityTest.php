<?php

namespace Tests\Feature;

use App\Exceptions\PaymentGatewayException;
use App\Models\Reservation;
use App\Services\PaymentGatewayService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class PaymentLoggingSecurityTest extends TestCase
{
    public function test_safe_logging_defaults_use_daily_info_rotation_for_fourteen_days(): void
    {
        $this->assertSame('null', config('logging.default'));
        $this->assertSame(['daily'], config('logging.channels.stack.channels'));
        $this->assertSame('info', config('logging.channels.daily.level'));
        $this->assertSame(14, (int) config('logging.channels.daily.days'));
    }

    public function test_paymongo_error_response_body_and_detail_are_not_logged_or_exposed(): void
    {
        $marker = 'guest-sensitive@example.com-4111111111111111';
        Http::fake([
            '*/checkout_sessions' => Http::response([
                'errors' => [[
                    'code' => 'parameter_invalid',
                    'detail' => $marker,
                ]],
            ], 422),
        ]);
        Log::spy();

        $reservation = new Reservation([
            'reference_number' => '2026-9001',
            'guest_name' => 'Sensitive Guest',
            'guest_email' => 'guest-sensitive@example.com',
            'guest_phone' => '09171234567',
        ]);
        $reservation->id = 9001;

        try {
            app(PaymentGatewayService::class)->createCheckoutSession(
                $reservation,
                1000,
                'deposit',
                ['gcash'],
                ['success' => 'https://app.uhlms.uk/success', 'cancel' => 'https://app.uhlms.uk/cancel'],
            );
            $this->fail('The simulated PayMongo failure was not thrown.');
        } catch (PaymentGatewayException $exception) {
            $this->assertStringNotContainsString($marker, $exception->getMessage());
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'PayMongo request failed',
                Mockery::on(function (array $context) use ($marker): bool {
                    return $context['operation'] === 'create_checkout_session'
                        && $context['status'] === 422
                        && $context['gateway_error_code'] === 'parameter_invalid'
                        && ! array_key_exists('body', $context)
                        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $marker);
                }),
            );
    }
}
