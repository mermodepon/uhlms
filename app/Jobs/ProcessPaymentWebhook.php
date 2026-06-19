<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\ReservationPayment;
use App\Notifications\NotificationHelper;
use App\Services\ReservationWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120]; // Exponential backoff: 30s, 60s, 120s

    /**
     * Webhook payload data.
     *
     * @var array
     */
    protected array $webhookData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $webhookData)
    {
        $this->webhookData = $webhookData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Extract event type
            $eventType = $this->webhookData['data']['attributes']['type'] ?? null;
            $eventData = $this->webhookData['data']['attributes']['data'] ?? null;

            if (! $eventData) {
                Log::error('Invalid webhook payload: missing event data', ['webhook' => $this->webhookData]);

                return;
            }

            // Handle source.chargeable events (e-wallet payments)
            if ($eventType === 'source.chargeable') {
                $this->handleSourceChargeable($eventData);

                return;
            }

            // Handle payment.paid events (direct payments or after source charge)
            if ($eventType === 'payment.paid') {
                $this->handlePaymentPaid($eventData);

                return;
            }

            if ($eventType === 'checkout_session.payment.paid') {
                $this->handleCheckoutSessionPaid($eventData);

                return;
            }

            Log::info('Unhandled webhook event type', ['type' => $eventType]);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle source.chargeable webhook event.
     * This occurs when a user completes payment via e-wallet (GCash, Maya, etc.).
     */
    protected function handleSourceChargeable(array $sourceData): void
    {
        $sourceId = $sourceData['id'] ?? null;
        $sourceAttributes = $sourceData['attributes'] ?? [];
        $amount = $sourceAttributes['amount'] ?? null;

        if (! $sourceId || ! $amount) {
            Log::error('Source chargeable event missing required data', [
                'source_id' => $sourceId,
                'amount' => $amount,
            ]);

            return;
        }

        Log::info('Processing source.chargeable event', [
            'source_id' => $sourceId,
            'amount' => $amount,
        ]);

        // Create a Payment from the chargeable Source
        $gatewayService = app(\App\Services\PaymentGatewayService::class);

        try {
            $paymentData = $gatewayService->createPaymentFromSource($sourceId, $amount);

            // Now process this payment as a payment.paid event
            $this->handlePaymentPaid($paymentData);
        } catch (\Exception $e) {
            Log::error('Failed to create payment from source', [
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle checkout_session.payment.paid webhook event.
     */
    protected function handleCheckoutSessionPaid(array $checkoutSessionData): void
    {
        $checkoutAttributes = $checkoutSessionData['attributes'] ?? [];
        $payments = $checkoutAttributes['payments'] ?? [];
        $paymentData = $payments[0] ?? null;

        if (! is_array($paymentData)) {
            Log::warning('Checkout session payment webhook missing payment payload', [
                'checkout_session_id' => $checkoutSessionData['id'] ?? null,
            ]);

            return;
        }

        $paymentData['attributes']['metadata'] = array_merge(
            $checkoutAttributes['metadata'] ?? [],
            $paymentData['attributes']['metadata'] ?? []
        );

        $paymentData['attributes']['checkout_session_id'] = $checkoutSessionData['id'] ?? null;

        $this->handlePaymentPaid($paymentData);
    }

    /**
     * Handle payment.paid webhook event.
     */
    protected function handlePaymentPaid(array $paymentData): void
    {
        try {
            $paymentId = $paymentData['id'] ?? null;
            $paymentStatus = $paymentData['attributes']['status'] ?? null;
            $paymentAmount = ($paymentData['attributes']['amount'] ?? 0) / 100; // Convert centavos to PHP
            $paymentIntentId = $paymentData['attributes']['payment_intent_id'] ?? null;
            $checkoutSessionId = $paymentData['attributes']['checkout_session_id'] ?? null;

            // Extract reservation ID from source metadata or payment attributes
            $sourceData = $paymentData['attributes']['source'] ?? [];
            $sourceAttributes = $sourceData['attributes'] ?? [];
            $metadata = $sourceAttributes['metadata'] ?? [];
            if (empty($metadata)) {
                $metadata = $sourceData['metadata'] ?? [];
            }
            if (empty($metadata)) {
                $metadata = $paymentData['attributes']['metadata'] ?? [];
            }
            $reservationId = $metadata['reservation_id'] ?? null;

            if (! $paymentId || ! $reservationId) {
                Log::error('Payment event missing payment_id or reservation_id', [
                    'payment_id' => $paymentId,
                    'metadata' => $metadata,
                ]);

                return;
            }

            // Find the reservation
            $reservation = Reservation::find($reservationId);

            if (! $reservation) {
                Log::error('Reservation not found for webhook', [
                    'reservation_id' => $reservationId,
                    'payment_id' => $paymentId,
                ]);

                return;
            }

            // Process payment within transaction
            DB::transaction(function () use ($paymentId, $paymentStatus, $paymentAmount, $paymentIntentId, $checkoutSessionId, $reservation, $paymentData, $sourceData, $sourceAttributes) {
            $sourceId = $sourceData['id'] ?? null;
            $gatewaySourceId = $sourceId ?: $checkoutSessionId;

            // Check if payment already processed (idempotency)
            // Look for existing payment by actual payment ID, payment intent ID, or source/checkout session ID.
            $existingPayment = ReservationPayment::where(function ($query) use ($paymentId, $paymentIntentId, $sourceId, $checkoutSessionId) {
                $query->where('gateway_payment_id', $paymentId);
                if ($paymentIntentId) {
                    $query->orWhere('gateway_payment_id', $paymentIntentId);
                }
                if ($sourceId) {
                    $query->orWhere('gateway_source_id', $sourceId);
                }
                if ($checkoutSessionId) {
                    $query->orWhere('gateway_source_id', $checkoutSessionId);
                }
            })->first();

            if ($existingPayment && $existingPayment->gateway_status === 'paid') {
                Log::info('Payment already processed (duplicate webhook)', [
                    'payment_id' => $paymentId,
                    'source_id' => $sourceId,
                    'reservation_id' => $reservation->id,
                ]);

                return;
            }

            // Detect payment method from source type
            $sourceType = $sourceAttributes['type'] ?? $sourceData['type'] ?? 'unknown';
            $paymentMethod = match ($sourceType) {
                'gcash' => 'GCash',
                'paymaya' => 'Maya',
                'grab_pay' => 'GrabPay',
                'card' => 'Card',
                'billease' => 'BillEase',
                'qrph' => 'QR Ph',
                default => ucfirst(str_replace('_', ' ', $sourceType)),
            };

            // Detect payment type from metadata
            $paymentMetadata = $paymentData['attributes']['metadata'] ?? [];
            $isDeposit = ($paymentMetadata['payment_type'] ?? 'deposit') === 'deposit';
            $wasCancelledBeforePayment = $existingPayment?->gateway_status === 'cancelled'
                || $existingPayment?->status === 'cancelled';
            if (($paymentMetadata['payment_type'] ?? null) === 'checkin_balance') {
                $paymentMethod = 'PayMongo Online';
            }

            if ($existingPayment) {
                // Update existing payment record
                $existingPayment->update([
                    'gateway_payment_id' => $paymentId,
                    'gateway_source_id' => $gatewaySourceId,
                    'gateway_status' => 'paid',
                    'status' => 'posted',
                    'payment_mode' => $paymentMethod,
                    'is_deposit' => $isDeposit,  // Preserve payment type
                    'reference_no' => $existingPayment->reference_no ?: "PM-{$paymentId}",
                    'or_date' => $existingPayment->or_date ?: now()->toDateString(),
                    'received_at' => $existingPayment->received_at ?: now(),
                    'gateway_metadata' => array_merge(
                        $existingPayment->gateway_metadata ?? [],
                        [
                            'webhook_received_at' => now()->toIso8601String(),
                            'payment_intent_id' => $paymentIntentId,
                            'checkout_session_id' => $checkoutSessionId,
                            'paid_after_staff_cancellation' => $wasCancelledBeforePayment,
                            'payment_data' => $paymentData,
                            'source_data' => $sourceData,
                        ]
                    ),
                ]);

                $payment = $existingPayment;
            } else {
                // Create new payment record
                $payment = ReservationPayment::create([
                    'reservation_id' => $reservation->id,
                    'amount' => $paymentAmount,
                    'payment_mode' => $paymentMethod,
                    'gateway' => 'paymongo',
                    'gateway_payment_id' => $paymentId,
                    'gateway_source_id' => $gatewaySourceId,
                    'gateway_status' => 'paid',
                    'is_deposit' => $isDeposit,  // Set based on metadata
                    'status' => 'posted',
                    'received_at' => now(),
                    'reference_no' => "PM-{$paymentId}",
                    'or_date' => now()->toDateString(),
                    'gateway_metadata' => [
                        'webhook_received_at' => now()->toIso8601String(),
                        'payment_intent_id' => $paymentIntentId,
                        'checkout_session_id' => $checkoutSessionId,
                        'payment_data' => $paymentData,
                        'source_data' => $sourceData,
                    ],
                    'meta' => [
                        'source' => 'online_payment_webhook',
                        'payment_type' => $paymentMetadata['payment_type'] ?? ($isDeposit ? 'deposit' : 'full'),
                    ],
                ]);
            }

                $reservation = app(ReservationWorkflowService::class)
                    ->markConfirmedFromOnlinePayment($reservation);

                // Refresh financial summary
                $reservation->refreshFinancialSummary();

                // Log the payment
                $paymentTypeLabel = $payment->is_deposit ? 'deposit' : 'full payment';
                ReservationLog::record(
                    $reservation,
                    'payment_completed',
                    "Online {$paymentTypeLabel} of ₱".number_format($paymentAmount, 2)." received via PayMongo ({$payment->payment_mode}).",
                    [
                        'payment_id' => $payment->id,
                    'gateway_payment_id' => $paymentId,
                    'amount' => $paymentAmount,
                    'gateway' => 'paymongo',
                    'is_deposit' => $payment->is_deposit,
                ]
                );

                // Notify staff of successful payment
                NotificationHelper::notifyAllStaff(
                    'Online Payment Received',
                    "Reservation #{$reservation->reference_number} received online payment of ₱".number_format($paymentAmount, 2).'.',
                    'success',
                    'payment',
                    route('filament.admin.resources.reservations.index', [], false).'?tableSearch='.urlencode($reservation->reference_number),
                    null, // No actor (system notification)
                    'reservations_view'
                );

                if ($wasCancelledBeforePayment) {
                    NotificationHelper::notifyAllStaff(
                        'Cancelled PayMongo Request Was Paid',
                        "Reservation #{$reservation->reference_number} received PayMongo payment of â‚±".number_format($paymentAmount, 2).' after staff had cancelled the checkout request. Please reconcile against any manual collection.',
                        'warning',
                        'payment',
                        route('filament.admin.resources.reservations.index', [], false).'?tableSearch='.urlencode($reservation->reference_number),
                        null,
                        'reservations_view'
                    );
                }

                Log::info('Payment webhook processed successfully', [
                    'reservation_id' => $reservation->id,
                    'payment_id' => $payment->id,
                    'gateway_payment_id' => $paymentId,
                    'amount' => $paymentAmount,
                    'new_status' => $reservation->status,
                    'paid_after_staff_cancellation' => $wasCancelledBeforePayment,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to process payment webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook' => $this->webhookData,
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Payment webhook processing failed permanently after retries', [
            'error' => $exception->getMessage(),
            'webhook' => $this->webhookData,
        ]);

        // Optionally notify admins of failed webhook processing
        try {
            $paymentData = $this->webhookData['data']['attributes']['data'] ?? [];
            $metadata = $paymentData['attributes']['metadata'] ?? [];
            $reservationId = $metadata['reservation_id'] ?? 'unknown';

            NotificationHelper::notifyAllStaff(
                'Payment Webhook Processing Failed',
                "Failed to process PayMongo webhook for reservation ID {$reservationId}. Manual review required.",
                'danger',
                'payment',
                null,
                null,
                'reservations_edit'
            );
        } catch (\Exception $e) {
            Log::error('Failed to send webhook failure notification', ['error' => $e->getMessage()]);
        }
    }
}
