<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentWebhookEvent;
use App\Models\Setting;
use App\Services\PaymentGatewayService;
use App\Support\PayMongoWebhookData;
use App\Support\SecurityMonitor;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PaymentWebhookController extends Controller
{
    private const HANDLED_EVENTS = [
        'source.chargeable',
        'payment.paid',
        'checkout_session.payment.paid',
    ];

    /**
     * Handle PayMongo webhook events.
     */
    public function handle(
        Request $request,
        PaymentGatewayService $gatewayService,
        Dispatcher $dispatcher,
    ): JsonResponse {
        // Always accept webhooks — they represent real money already charged.
        // The toggle only prevents NEW payment initiations, not confirmations.
        if (! Setting::isOnlinePaymentsEnabled()) {
            Log::info('PayMongo webhook received while online payments are disabled — processing anyway');
        }

        // Get raw payload for signature verification
        $payload = $request->getContent();
        $signature = $request->header('PayMongo-Signature');

        // Verify webhook signature
        if (! $gatewayService->verifyWebhookSignature($payload, $signature ?? '')) {
            $this->logInvalidSignatureAttempt($request);
            app(SecurityMonitor::class)->webhook('invalid_signature');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // Parse the webhook data
        $webhookData = $request->json()->all();
        $eventId = data_get($webhookData, 'data.id');
        $eventResourceType = data_get($webhookData, 'data.type');
        $eventType = $webhookData['data']['attributes']['type'] ?? null;

        if (
            ! is_string($eventId)
            || strlen($eventId) > 255
            || preg_match('/\Aevt_[A-Za-z0-9_-]+\z/', $eventId) !== 1
            || $eventResourceType !== 'event'
            || ! is_string($eventType)
            || $eventType === ''
            || strlen($eventType) > 100
        ) {
            app(SecurityMonitor::class)->webhook('malformed_envelope');
            Log::warning('Invalid PayMongo webhook event envelope', [
                'event_id' => is_scalar($eventId) ? $eventId : null,
                'event_resource_type' => is_scalar($eventResourceType) ? $eventResourceType : null,
                'event_type' => is_scalar($eventType) ? $eventType : null,
            ]);

            return response()->json(['message' => 'Invalid event payload'], 400);
        }

        $payloadHash = hash('sha256', $payload);
        $signatureTimestamp = $gatewayService->webhookSignatureTimestamp($signature ?? '');
        $livemode = data_get($webhookData, 'data.attributes.livemode');
        $livemode = is_bool($livemode) ? $livemode : null;
        $handled = in_array($eventType, self::HANDLED_EVENTS, true);

        try {
            [$eventReceipt, $created] = DB::transaction(function () use (
            $dispatcher,
            $eventId,
            $eventType,
            $handled,
            $livemode,
            $payloadHash,
            $signatureTimestamp,
            $webhookData,
        ): array {
            $eventReceipt = PaymentWebhookEvent::firstOrCreate(
                [
                    'gateway' => 'paymongo',
                    'event_id' => $eventId,
                ],
                [
                    'event_type' => $eventType,
                    'livemode' => $livemode,
                    'payload_sha256' => $payloadHash,
                    'signature_timestamp' => $signatureTimestamp,
                    'status' => $handled
                        ? PaymentWebhookEvent::STATUS_QUEUED
                        : PaymentWebhookEvent::STATUS_IGNORED,
                    'processed_at' => $handled ? null : now(),
                ],
            );

            if (! $eventReceipt->wasRecentlyCreated) {
                return [$eventReceipt, false];
            }

            if ($handled) {
                $dispatcher->dispatch(new ProcessPaymentWebhook(
                    PayMongoWebhookData::forQueue($webhookData),
                    $eventReceipt->id,
                ));
            }

            return [$eventReceipt, true];
            });
        } catch (\Throwable $exception) {
            app(SecurityMonitor::class)->webhook('dispatch_failure');
            throw $exception;
        }

        if (! $created) {
            if (! hash_equals($eventReceipt->payload_sha256, $payloadHash)) {
                app(SecurityMonitor::class)->webhook('event_id_collision');
                Log::warning('PayMongo webhook event ID was reused with a different payload', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'stored_payload_sha256' => $eventReceipt->payload_sha256,
                    'received_payload_sha256' => $payloadHash,
                ]);
            }

            return response()->json(['message' => 'Webhook received'], 200);
        }

        if (! $handled) {
            Log::info('PayMongo webhook event ignored', [
                'event_id' => $eventId,
                'type' => $eventType,
            ]);

            return response()->json(['message' => 'Webhook received'], 200);
        }

        Log::info('PayMongo webhook queued for processing', [
            'receipt_id' => $eventReceipt->id,
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        // Return 200 OK immediately to acknowledge receipt
        return response()->json(['message' => 'Webhook received'], 200);
    }

    private function logInvalidSignatureAttempt(Request $request): void
    {
        try {
            $sourceKey = hash_hmac(
                'sha256',
                (string) ($request->ip() ?? 'unknown'),
                (string) config('app.key'),
            );

            RateLimiter::attempt(
                'paymongo-invalid-signature:'.$sourceKey,
                1,
                static fn () => Log::warning('Invalid PayMongo webhook signature rejected'),
                300,
            );
        } catch (\Throwable) {
            // Logging and rate limiting must never affect the generic 401 response.
        }
    }
}
