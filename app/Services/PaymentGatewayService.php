<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Reservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    private const GUEST_PAYMENT_METHODS = [
        'gcash' => [
            'label' => 'GCash',
            'description' => 'Pay via GCash wallet.',
            'badge' => 'GCash',
            'badge_color' => 'blue',
        ],
        'paymaya' => [
            'label' => 'Maya (PayMaya)',
            'description' => 'Pay via Maya wallet.',
            'badge' => 'Maya',
            'badge_color' => 'green',
        ],
        'grab_pay' => [
            'label' => 'GrabPay',
            'description' => 'Pay via GrabPay wallet.',
            'badge' => 'GrabPay',
            'badge_color' => 'emerald',
        ],
        'card' => [
            'label' => 'Credit / Debit Card',
            'description' => 'Pay with Visa or Mastercard on PayMongo Checkout.',
            'badge' => 'Card',
            'badge_color' => 'slate',
        ],
        'billease' => [
            'label' => 'BillEase',
            'description' => 'Buy now, pay later through BillEase if enabled on your account.',
            'badge' => 'BNPL',
            'badge_color' => 'amber',
        ],
        'qrph' => [
            'label' => 'QR Ph',
            'description' => 'Scan a QR Ph code from the hosted PayMongo checkout page.',
            'badge' => 'QR Ph',
            'badge_color' => 'violet',
        ],
    ];

    /**
     * PayMongo API base URL.
     */
    private string $baseUrl;

    /**
     * PayMongo secret key for authentication.
     */
    private string $secretKey;

    /**
     * Webhook signature secret.
     */
    private string $webhookSecret;

    /**
     * Initialize the service with config values.
     */
    public function __construct()
    {
        $this->baseUrl = config('paymongo.api_base_url');
        $this->secretKey = config('paymongo.secret_key');
        $this->webhookSecret = config('paymongo.webhook_secret');
    }

    /**
     * Create HTTP client with appropriate SSL settings for environment.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function httpClient()
    {
        $client = Http::withBasicAuth($this->secretKey, '')
            ->timeout(30);

        // Disable SSL verification for local development only
        // WARNING: Never use this in production!
        if (app()->environment('local')) {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }

    /**
     * Create a PaymentIntent for the reservation.
     *
     * @param  Reservation  $reservation
     * @param  float  $amount  Amount in PHP
     * @param  string  $paymentType  'deposit' or 'full'
     * @return array ['payment_id' => string, 'checkout_url' => string, 'client_key' => string]
     *
     * @throws PaymentGatewayException
     */
    public function createPaymentIntent(Reservation $reservation, float $amount, string $paymentType = 'deposit'): array
    {
        try {
            $amountInCentavos = (int) ($amount * 100);
            $description = $paymentType === 'full' 
                ? "Full Payment for Reservation {$reservation->reference_number}"
                : "Deposit for Reservation {$reservation->reference_number}";

            $payload = [
                'data' => [
                    'attributes' => [
                        'amount' => $amountInCentavos,
                        'currency' => 'PHP',
                        'payment_method_allowed' => config('paymongo.payment_methods', ['gcash', 'paymaya', 'card', 'grab_pay']),
                        'payment_method_options' => [
                            'card' => [
                                'request_three_d_secure' => 'any',
                            ],
                        ],
                        'description' => $description,
                        'statement_descriptor' => 'UHLMS Homestay',
                        'metadata' => [
                            'reservation_id' => (string) $reservation->id,
                            'reservation_ref' => (string) $reservation->reference_number,
                            'guest_email' => (string) ($reservation->guest_email ?? ''),
                            'guest_name' => (string) ($reservation->guest_name ?? ''),
                            'payment_type' => $paymentType,
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient()
                ->post("{$this->baseUrl}/payment_intents", $payload);

            if ($response->failed()) {
                $errorMessage = $response->json('errors.0.detail') ?? 'Unknown error creating payment intent';
                Log::error('PayMongo API Error (Create Intent)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'reservation_id' => $reservation->id,
                ]);

                throw new PaymentGatewayException("Failed to create payment: {$errorMessage}");
            }

            $data = $response->json('data');

            return [
                'payment_id' => $data['id'] ?? null,
                'client_key' => $data['attributes']['client_key'] ?? null,
                'status' => $data['attributes']['status'] ?? null,
                'amount' => $amount,
            ];
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayMongo Service Exception', [
                'message' => $e->getMessage(),
                'reservation_id' => $reservation->id,
            ]);

            throw new PaymentGatewayException("Payment service error: {$e->getMessage()}");
        }
    }

    /**
     * Create a hosted PayMongo Checkout Session.
     *
     * @param  Reservation  $reservation
     * @param  float  $amount
     * @param  string  $paymentType
     * @param  array<int, string>|null  $paymentMethods
     * @param  array{success:string,cancel:string}  $returnUrls
     * @return array{checkout_session_id:?string,checkout_url:?string,payment_intent_id:?string,payment_method_types:array<int,string>}
     *
     * @throws PaymentGatewayException
     */
    public function createCheckoutSession(
        Reservation $reservation,
        float $amount,
        string $paymentType = 'deposit',
        ?array $paymentMethods = null,
        array $returnUrls = []
    ): array {
        try {
            $amountInCentavos = (int) round($amount * 100);
            $paymentTypeLabel = $paymentType === 'full' ? 'Full Payment' : 'Deposit';
            $description = "{$paymentTypeLabel} for Reservation {$reservation->reference_number}";
            $paymentMethodTypes = array_values(array_unique($paymentMethods ?: config('paymongo.checkout_payment_methods', [])));

            if (empty($paymentMethodTypes)) {
                throw new PaymentGatewayException('No checkout payment methods are configured.');
            }

            $payload = [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => (string) ($reservation->guest_name ?? ''),
                            'email' => (string) ($reservation->guest_email ?? ''),
                            'phone' => (string) ($reservation->guest_phone ?? ''),
                        ],
                        'cancel_url' => $returnUrls['cancel'] ?? url('/reserve/payment-failed'),
                        'success_url' => $returnUrls['success'] ?? url('/reserve/payment-success'),
                        'description' => $description,
                        'reference_number' => (string) $reservation->reference_number,
                        'send_email_receipt' => filled($reservation->guest_email),
                        'show_description' => true,
                        'show_line_items' => true,
                        'line_items' => [[
                            'amount' => $amountInCentavos,
                            'currency' => 'PHP',
                            'description' => $description,
                            'name' => $paymentType === 'full' ? 'Reservation Full Payment' : 'Reservation Deposit',
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => $paymentMethodTypes,
                        'metadata' => [
                            'reservation_id' => (string) $reservation->id,
                            'reservation_ref' => (string) $reservation->reference_number,
                            'guest_email' => (string) ($reservation->guest_email ?? ''),
                            'guest_name' => (string) ($reservation->guest_name ?? ''),
                            'payment_type' => $paymentType,
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient()
                ->post("{$this->baseUrl}/checkout_sessions", $payload);

            if ($response->failed()) {
                $errorMessage = $response->json('errors.0.detail') ?? 'Unknown error creating checkout session';
                Log::error('PayMongo API Error (Create Checkout Session)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'reservation_id' => $reservation->id,
                    'payment_method_types' => $paymentMethodTypes,
                ]);

                throw new PaymentGatewayException("Failed to create checkout session: {$errorMessage}");
            }

            $data = $response->json('data');

            return [
                'checkout_session_id' => $data['id'] ?? null,
                'checkout_url' => $data['attributes']['checkout_url'] ?? null,
                'payment_intent_id' => $data['attributes']['payment_intent']['id'] ?? null,
                'payment_method_types' => $data['attributes']['payment_method_types'] ?? $paymentMethodTypes,
            ];
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayMongo Checkout Session Exception', [
                'message' => $e->getMessage(),
                'reservation_id' => $reservation->id,
            ]);

            throw new PaymentGatewayException("Checkout session error: {$e->getMessage()}");
        }
    }

    /**
     * Attach a payment method (GCash, etc.) to a PaymentIntent.
     * For e-wallets, this creates a standalone Source.
     *
     * @param  string  $paymentIntentId
     * @param  string  $paymentMethod  'gcash', 'paymaya', 'grab_pay', or card details
     * @param  array  $returnUrls  ['success' => url, 'failed' => url]
     * @return array ['checkout_url' => string, 'source_id' => string]
     *
     * @throws PaymentGatewayException
     */
    public function attachPaymentMethod(string $paymentIntentId, string $paymentMethod, array $returnUrls): array
    {
        try {
            // Get payment intent to extract amount and metadata
            $paymentIntent = $this->retrievePayment($paymentIntentId);
            $amount = $paymentIntent['attributes']['amount'] ?? 0;
            $metadata = $paymentIntent['attributes']['metadata'] ?? [];

            // Create a source for e-wallet payments (GCash, Maya, GrabPay)
            // Sources are standalone and don't need to be attached to PaymentIntent
            // Include metadata so we can track the reservation in webhooks
            $sourcePayload = [
                'data' => [
                    'attributes' => [
                        'type' => $paymentMethod,
                        'amount' => $amount,
                        'currency' => 'PHP',
                        'redirect' => [
                            'success' => $returnUrls['success'],
                            'failed' => $returnUrls['failed'],
                        ],
                        'metadata' => $metadata, // Pass reservation info to source
                    ],
                ],
            ];

            $sourceResponse = $this->httpClient()
                ->post("{$this->baseUrl}/sources", $sourcePayload);

            if ($sourceResponse->failed()) {
                $errorMessage = $sourceResponse->json('errors.0.detail') ?? 'Unknown error creating source';
                Log::error('PayMongo Source Creation Failed', [
                    'status' => $sourceResponse->status(),
                    'body' => $sourceResponse->body(),
                ]);
                throw new PaymentGatewayException("Failed to create payment source: {$errorMessage}");
            }

            $sourceData = $sourceResponse->json('data');
            $sourceId = $sourceData['id'];
            $checkoutUrl = $sourceData['attributes']['redirect']['checkout_url'] ?? null;

            if (!$checkoutUrl) {
                throw new PaymentGatewayException('No checkout URL returned from payment gateway');
            }

            return [
                'checkout_url' => $checkoutUrl,
                'source_id' => $sourceId,
            ];
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayMongo Attach Method Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new PaymentGatewayException("Payment method attachment error: {$e->getMessage()}");
        }
    }

    /**
     * Retrieve a payment's current status from PayMongo.
     *
     * @param  string  $paymentId  PaymentIntent ID
     * @return array Payment data from API
     *
     * @throws PaymentGatewayException
     */
    public function retrievePayment(string $paymentId): array
    {
        try {
            $response = $this->httpClient()
                ->get("{$this->baseUrl}/payment_intents/{$paymentId}");

            if ($response->failed()) {
                $errorMessage = $response->json('errors.0.detail') ?? 'Unknown error retrieving payment';
                throw new PaymentGatewayException("Failed to retrieve payment: {$errorMessage}");
            }

            return $response->json('data');
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayMongo Retrieve Exception', ['message' => $e->getMessage(), 'payment_id' => $paymentId]);

            throw new PaymentGatewayException("Payment retrieval error: {$e->getMessage()}");
        }
    }

    /**
     * Create a Payment from a chargeable Source (for e-wallet payments).
     *
     * @param  string  $sourceId  Source ID from webhook
     * @param  int  $amount  Amount in cents (centavos)
     * @return array Payment data
     *
     * @throws PaymentGatewayException
     */
    public function createPaymentFromSource(string $sourceId, int $amount): array
    {
        try {
            $payload = [
                'data' => [
                    'attributes' => [
                        'amount' => $amount,
                        'source' => [
                            'id' => $sourceId,
                            'type' => 'source',
                        ],
                        'currency' => 'PHP',
                    ],
                ],
            ];

            $response = $this->httpClient()
                ->post("{$this->baseUrl}/payments", $payload);

            if ($response->failed()) {
                $errorMessage = $response->json('errors.0.detail') ?? 'Unknown error creating payment';
                Log::error('PayMongo Payment Creation Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'source_id' => $sourceId,
                ]);
                throw new PaymentGatewayException("Failed to create payment from source: {$errorMessage}");
            }

            return $response->json('data');
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayMongo Create Payment Exception', [
                'message' => $e->getMessage(),
                'source_id' => $sourceId,
            ]);

            throw new PaymentGatewayException("Payment creation error: {$e->getMessage()}");
        }
    }

    /**
     * Verify PayMongo webhook signature using HMAC-SHA256.
     *
     * @param  string  $payload  Raw webhook payload (request body)
     * @param  string  $signature  Signature from PayMongo-Signature header
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // Skip verification if strict mode is disabled (for development)
        if (! config('paymongo.strict_webhook_verification', true)) {
            Log::info('Webhook signature verification skipped (strict mode disabled)');
            return true;
        }

        if (empty($this->webhookSecret)) {
            Log::warning('Webhook secret not configured; signature verification skipped');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Calculate deposit amount for a reservation.
     *
     * @param  Reservation  $reservation
     * @return float Deposit amount in PHP
     */
    public function calculateDepositAmount(Reservation $reservation): float
    {
        return $reservation->calculateDepositAmount();
    }

    /**
     * @return array<string, array{label:string,description:string,badge:string,badge_color:string}>
     */
    public function getGuestPaymentMethods(): array
    {
        return self::GUEST_PAYMENT_METHODS;
    }

    /**
     * @return array<int, string>
     */
    public function getMerchantPaymentMethods(): array
    {
        try {
            $response = $this->httpClient()
                ->get("{$this->baseUrl}/merchants/capabilities/payment_methods");

            if ($response->failed()) {
                return [];
            }

            $payload = $response->json();

            if (is_array($payload) && array_is_list($payload)) {
                return array_values(array_filter($payload, 'is_string'));
            }

            $data = $payload['data'] ?? [];

            if (is_array($data) && array_is_list($data)) {
                return array_values(array_filter($data, 'is_string'));
            }

            return [];
        } catch (\Exception $e) {
            Log::warning('Unable to retrieve PayMongo merchant payment methods', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get PaymentIntent amount (helper for attach method).
     *
     * @throws PaymentGatewayException
     */
    private function getPaymentIntentAmount(string $paymentIntentId): int
    {
        $paymentData = $this->retrievePayment($paymentIntentId);

        return $paymentData['attributes']['amount'] ?? 0;
    }

    /**
     * Get PaymentIntent client key (helper for attach method).
     *
     * @throws PaymentGatewayException
     */
    private function getPaymentIntentClientKey(string $paymentIntentId): string
    {
        $paymentData = $this->retrievePayment($paymentIntentId);

        return $paymentData['attributes']['client_key'] ?? '';
    }
}
