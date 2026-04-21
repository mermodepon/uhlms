<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentWebhook;
use App\Models\Setting;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle PayMongo webhook events.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        // Always accept webhooks — they represent real money already charged.
        // The toggle only prevents NEW payment initiations, not confirmations.
        if (! Setting::isOnlinePaymentsEnabled()) {
            Log::info('PayMongo webhook received while online payments are disabled — processing anyway');
        }

        // Get raw payload for signature verification
        $payload = $request->getContent();
        $signature = $request->header('PayMongo-Signature');

        // Verify webhook signature
        $gatewayService = app(PaymentGatewayService::class);

        if (! $gatewayService->verifyWebhookSignature($payload, $signature ?? '')) {
            Log::warning('Invalid PayMongo webhook signature', [
                'ip' => $request->ip(),
                'signature' => $signature,
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // Parse the webhook data
        $webhookData = $request->json()->all();
        $eventType = $webhookData['data']['attributes']['type'] ?? null;

        // Handle both source.chargeable (e-wallets) and payment.paid events
        if (! in_array($eventType, ['source.chargeable', 'payment.paid'])) {
            Log::info('PayMongo webhook event ignored', ['type' => $eventType]);

            return response()->json(['message' => 'Event type not handled'], 200);
        }

        // Dispatch job to process webhook asynchronously
        ProcessPaymentWebhook::dispatch($webhookData);

        Log::info('PayMongo webhook queued for processing', [
            'event_type' => $eventType,
            'source_id' => $webhookData['data']['attributes']['data']['id'] ?? 'unknown',
        ]);

        // Return 200 OK immediately to acknowledge receipt
        return response()->json(['message' => 'Webhook received'], 200);
    }
}
