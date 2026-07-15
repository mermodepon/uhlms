<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Reservation;
use Illuminate\Http\Client\Response;
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

        $caBundle = config('paymongo.ca_bundle');
        if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
            $client = $client->withOptions(['verify' => $caBundle]);
        }

        // Only allow insecure TLS when a developer explicitly opts in for local troubleshooting.
        if (app()->environment('local') && config('paymongo.allow_insecure_tls', false)) {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }

    /**
     * Create a PaymentIntent for the reservation.
     *
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
                $this->logGatewayFailure('create_payment_intent', $response, $reservation->id);

                throw new PaymentGatewayException('The payment provider could not start this payment. Please try again.');
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
            $this->logGatewayException('create_payment_intent', $e, $reservation->id);

            throw new PaymentGatewayException('The payment provider could not start this payment. Please try again.');
        }
    }

    /**
     * Create a hosted PayMongo Checkout Session.
     *
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
            $paymentTypeLabel = match ($paymentType) {
                'full' => 'Full Payment',
                'checkin_balance' => 'Check-in Balance',
                default => 'Deposit',
            };
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
                            'name' => match ($paymentType) {
                                'full' => 'Reservation Full Payment',
                                'checkin_balance' => 'Reservation Check-in Balance',
                                default => 'Reservation Deposit',
                            },
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
                $this->logGatewayFailure('create_checkout_session', $response, $reservation->id);

                throw new PaymentGatewayException('The payment provider could not start checkout. Please try again.');
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
            $this->logGatewayException('create_checkout_session', $e, $reservation->id);

            throw new PaymentGatewayException('The payment provider could not start checkout. Please try again.');
        }
    }

    /**
     * Attach a payment method (GCash, etc.) to a PaymentIntent.
     * For e-wallets, this creates a standalone Source.
     *
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
                $this->logGatewayFailure('create_payment_source', $sourceResponse);
                throw new PaymentGatewayException('The payment provider could not prepare this payment method. Please try again.');
            }

            $sourceData = $sourceResponse->json('data');
            $sourceId = $sourceData['id'];
            $checkoutUrl = $sourceData['attributes']['redirect']['checkout_url'] ?? null;

            if (! $checkoutUrl) {
                throw new PaymentGatewayException('No checkout URL returned from payment gateway');
            }

            return [
                'checkout_url' => $checkoutUrl,
                'source_id' => $sourceId,
            ];
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logGatewayException('create_payment_source', $e);

            throw new PaymentGatewayException('The payment provider could not prepare this payment method. Please try again.');
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
                $this->logGatewayFailure('retrieve_payment', $response);
                throw new PaymentGatewayException('The payment provider could not retrieve this payment. Please try again.');
            }

            return $response->json('data');
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logGatewayException('retrieve_payment', $e);

            throw new PaymentGatewayException('The payment provider could not retrieve this payment. Please try again.');
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
                $this->logGatewayFailure('create_payment_from_source', $response);
                throw new PaymentGatewayException('The payment provider could not complete this payment. Please try again.');
            }

            return $response->json('data');
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logGatewayException('create_payment_from_source', $e);

            throw new PaymentGatewayException('The payment provider could not complete this payment. Please try again.');
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

        // PayMongo-Signature header format: "t=<timestamp>,te=<test_hmac>,v1=<live_hmac>"
        // The signed message is "<timestamp>.<raw_payload>" hashed with the webhook secret.
        $parts = $this->parseWebhookSignature($signature);

        $timestamp = $parts['t'] ?? '';
        if (empty($timestamp)) {
            return false;
        }

        $timestampValue = $this->webhookSignatureTimestamp($signature);
        if ($timestampValue === null) {
            return false;
        }

        $tolerance = max(0, (int) config('paymongo.webhook_tolerance_seconds', 300));
        $clockDifference = abs(now()->getTimestamp() - $timestampValue);
        if ($clockDifference > $tolerance) {
            return false;
        }

        $signedMessage = $timestamp.'.'.$payload;
        $expectedHmac = hash_hmac('sha256', $signedMessage, $this->webhookSecret);

        // Accept either test-mode (te) or live-mode (v1) signature component.
        $testSig = $parts['te'] ?? '';
        $liveSig = $parts['v1'] ?? '';

        $validTest = $testSig !== '' && hash_equals($expectedHmac, $testSig);
        $validLive = $liveSig !== '' && hash_equals($expectedHmac, $liveSig);

        return $validTest || $validLive;
    }

    public function webhookSignatureTimestamp(string $signature): ?int
    {
        $timestamp = $this->parseWebhookSignature($signature)['t'] ?? '';

        if (! is_string($timestamp) || preg_match('/\A[1-9]\d{0,18}\z/', $timestamp) !== 1) {
            return null;
        }

        $value = filter_var($timestamp, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $value === false ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function parseWebhookSignature(string $signature): array
    {
        $parts = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[trim($key)] = trim($value);
        }

        return $parts;
    }

    /**
     * Calculate deposit amount for a reservation.
     *
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
                'error_type' => class_basename($e),
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

    private function logGatewayFailure(string $operation, Response $response, ?int $reservationId = null): void
    {
        $gatewayCode = $response->json('errors.0.code');
        $context = [
            'operation' => $operation,
            'status' => $response->status(),
            'reservation_id' => $reservationId,
        ];

        if (is_scalar($gatewayCode)) {
            $context['gateway_error_code'] = (string) $gatewayCode;
        }

        Log::error('PayMongo request failed', $context);
    }

    private function logGatewayException(string $operation, \Throwable $exception, ?int $reservationId = null): void
    {
        Log::error('PayMongo request raised an exception', [
            'operation' => $operation,
            'reservation_id' => $reservationId,
            'error_type' => class_basename($exception),
        ]);
    }
}
