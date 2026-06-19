<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paymongo.webhook_secret' => 'test-webhook-secret',
            'paymongo.strict_webhook_verification' => true,
        ]);
    }

    public function test_paymongo_webhook_rejects_missing_signature(): void
    {
        Bus::fake();

        $response = $this->postJson(route('webhook.paymongo'), $this->payload());

        $response->assertUnauthorized();
        Bus::assertNotDispatched(ProcessPaymentWebhook::class);
    }

    public function test_paymongo_webhook_rejects_invalid_signature(): void
    {
        Bus::fake();

        $response = $this->withHeader('PayMongo-Signature', 'invalid-signature')
            ->postJson(route('webhook.paymongo'), $this->payload());

        $response->assertUnauthorized();
        Bus::assertNotDispatched(ProcessPaymentWebhook::class);
    }

    public function test_paymongo_webhook_accepts_valid_signature(): void
    {
        Bus::fake();

        $payload = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'test-webhook-secret');

        $response = $this->withHeader('PayMongo-Signature', $signature)
            ->call(
                method: 'POST',
                uri: route('webhook.paymongo'),
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_PAYMONGO_SIGNATURE' => $signature,
                ],
                content: $payload,
            );

        $response->assertOk();
        Bus::assertDispatched(ProcessPaymentWebhook::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'data' => [
                'attributes' => [
                    'type' => 'payment.paid',
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
}
