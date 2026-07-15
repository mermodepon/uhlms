<?php

namespace App\Jobs;

use App\Mail\GuestAccountInvitationMail;
use App\Models\PaymentWebhookEvent;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\ReservationPayment;
use App\Notifications\NotificationHelper;
use App\Services\ReservationWorkflowService;
use App\Services\ReservationAccountLinker;
use App\Support\PayMongoPaymentMetadata;
use App\Support\PayMongoWebhookData;
use App\Support\SecurityMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProcessPaymentWebhook implements ShouldBeEncrypted, ShouldQueue
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
     */
    protected array $webhookData;

    /**
     * Nullable so jobs serialized before event receipts were introduced remain usable.
     */
    protected ?int $webhookEventRecordId = null;

    protected ?string $processingFailureReason = null;

    /**
     * Create a new job instance.
     */
    public function __construct(array $webhookData, ?int $webhookEventRecordId = null)
    {
        $this->webhookData = PayMongoWebhookData::forQueue($webhookData);
        $this->webhookEventRecordId = $webhookEventRecordId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->markReceiptProcessing();

        try {
            // Re-normalizing also keeps jobs serialized before this deployment executable.
            $event = PayMongoWebhookData::forQueue($this->webhookData);
            $eventId = $event['event_id'] ?? null;
            $eventType = $event['event_type'] ?? null;
            $eventData = $event['resource'] ?? null;

            if (! $eventData) {
                Log::error('Invalid webhook payload: missing event data', [
                    'receipt_id' => $this->webhookEventRecordId,
                    'event_type' => $eventType,
                ]);
                $this->markReceiptFailed('Webhook payload is missing event data.');

                return;
            }

            $processed = match ($eventType) {
                'source.chargeable' => $this->handleSourceChargeable($eventData, $eventId),
                'payment.paid' => $this->handlePaymentPaid($eventData, $eventId),
                'checkout_session.payment.paid' => $this->handleCheckoutSessionPaid($eventData, $eventId),
                default => null,
            };

            if ($processed === null) {
                Log::info('Unhandled webhook event type', ['type' => $eventType]);
                $this->markReceiptIgnored();

                return;
            }

            if (! $processed) {
                $this->markReceiptFailed(
                    $this->processingFailureReason ?? 'Webhook event could not be processed.',
                );

                return;
            }

            $this->markReceiptProcessed();
        } catch (\Throwable $e) {
            $this->markReceiptRetrying($this->safeExceptionMessage($e));
            Log::error('Webhook processing attempt failed', [
                'receipt_id' => $this->webhookEventRecordId,
                'event_type' => $eventType ?? null,
                'error_type' => class_basename($e),
            ]);

            throw $e;
        }
    }

    /**
     * Handle source.chargeable webhook event.
     * This occurs when a user completes payment via e-wallet (GCash, Maya, etc.).
     */
    protected function handleSourceChargeable(array $sourceData, ?string $eventId = null): bool
    {
        $sourceId = $sourceData['id'] ?? null;
        $amount = $sourceData['amount'] ?? null;

        if (! $sourceId || ! $amount) {
            $this->processingFailureReason = 'Source chargeable event is missing its source ID or amount.';
            Log::error('Source chargeable event missing required data', [
                'receipt_id' => $this->webhookEventRecordId,
            ]);

            return false;
        }

        // Create a Payment from the chargeable Source
        $gatewayService = app(\App\Services\PaymentGatewayService::class);
        $paymentData = $gatewayService->createPaymentFromSource($sourceId, $amount);

        // The API response is normalized before any persistence or logging.
        return $this->handlePaymentPaid(PayMongoWebhookData::payment($paymentData), $eventId);
    }

    /**
     * Handle checkout_session.payment.paid webhook event.
     */
    protected function handleCheckoutSessionPaid(array $checkoutSessionData, ?string $eventId = null): bool
    {
        $paymentData = $checkoutSessionData['payment'] ?? null;

        if (! is_array($paymentData)) {
            $this->processingFailureReason = 'Checkout session event is missing its payment payload.';
            Log::warning('Checkout session payment webhook missing payment payload', [
                'receipt_id' => $this->webhookEventRecordId,
            ]);

            return false;
        }

        $paymentData['metadata'] = array_merge(
            $checkoutSessionData['metadata'] ?? [],
            $paymentData['metadata'] ?? [],
        );

        $paymentData['checkout_session_id'] = $checkoutSessionData['id'] ?? null;

        return $this->handlePaymentPaid($paymentData, $eventId);
    }

    /**
     * Handle payment.paid webhook event.
     */
    protected function handlePaymentPaid(array $paymentData, ?string $eventId = null): bool
    {
        $paymentId = $paymentData['id'] ?? null;
        $paymentAmount = ($paymentData['amount'] ?? 0) / 100; // Convert centavos to PHP
        $paymentIntentId = $paymentData['payment_intent_id'] ?? null;
        $checkoutSessionId = $paymentData['checkout_session_id'] ?? null;
        $sourceData = is_array($paymentData['source'] ?? null) ? $paymentData['source'] : [];
        $metadata = is_array($paymentData['metadata'] ?? null) ? $paymentData['metadata'] : [];
        $reservationId = $metadata['reservation_id'] ?? null;

        if (! $paymentId || ! $reservationId) {
            $this->processingFailureReason = 'Payment event is missing its payment ID or reservation ID.';
            Log::error('Payment event missing payment_id or reservation_id', [
                'receipt_id' => $this->webhookEventRecordId,
            ]);

            return false;
        }

        // Find the reservation
        $reservation = Reservation::find($reservationId);

        if (! $reservation) {
            $this->processingFailureReason = 'The reservation referenced by the payment event was not found.';
            Log::error('Reservation not found for webhook', [
                'reservation_id' => $reservationId,
                'receipt_id' => $this->webhookEventRecordId,
            ]);

            return false;
        }

        $shouldSendAccountInvitation = false;
        $invitationReservation = null;
        $invitationPayment = null;

        // Process payment within transaction
        $processed = DB::transaction(function () use ($paymentId, $paymentAmount, $paymentIntentId, $checkoutSessionId, $reservation, $sourceData, $metadata, $eventId, &$shouldSendAccountInvitation, &$invitationReservation, &$invitationPayment): bool {
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
                    'receipt_id' => $this->webhookEventRecordId,
                    'reservation_id' => $reservation->id,
                ]);

                return true;
            }

            // Detect payment method from source type
            $sourceType = $sourceData['type'] ?? 'unknown';
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
            $paymentMetadata = $metadata;
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
                    'gateway_metadata' => PayMongoPaymentMetadata::sanitize(
                        array_merge($existingPayment->gateway_metadata ?? [], [
                            'webhook_received_at' => now()->toIso8601String(),
                            'webhook_event_id' => $eventId,
                            'payment_intent_id' => $paymentIntentId,
                            'checkout_session_id' => $checkoutSessionId,
                            'payment_type' => $paymentMetadata['payment_type'] ?? null,
                            'paid_after_staff_cancellation' => $wasCancelledBeforePayment,
                        ]),
                        'paid',
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
                    'gateway_metadata' => PayMongoPaymentMetadata::sanitize([
                        'webhook_received_at' => now()->toIso8601String(),
                        'webhook_event_id' => $eventId,
                        'payment_intent_id' => $paymentIntentId,
                        'checkout_session_id' => $checkoutSessionId,
                        'payment_type' => $paymentMetadata['payment_type'] ?? null,
                    ], 'paid'),
                    'meta' => [
                        'source' => 'online_payment_webhook',
                        'payment_type' => $paymentMetadata['payment_type'] ?? ($isDeposit ? 'deposit' : 'full'),
                    ],
                ]);
            }

            $reservation = app(ReservationWorkflowService::class)
                ->markConfirmedFromOnlinePayment($reservation);

            $linker = app(ReservationAccountLinker::class);
            $linker->link($reservation);
            $reservation->refresh();

            if (($paymentMetadata['payment_type'] ?? null) === 'checkin_balance'
                && $linker->shouldInviteToCreateAccount($reservation)) {
                $shouldSendAccountInvitation = true;
                $invitationReservation = $reservation;
                $invitationPayment = $payment;
            }

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
                'receipt_id' => $this->webhookEventRecordId,
                'reservation_id' => $reservation->id,
                'payment_record_id' => $payment->id,
                'new_status' => $reservation->status,
                'paid_after_staff_cancellation' => $wasCancelledBeforePayment,
            ]);

            return true;
        });

        if ($processed && $shouldSendAccountInvitation && $invitationReservation && $invitationPayment) {
            try {
                Mail::to($invitationReservation->guest_email)
                    ->send(new GuestAccountInvitationMail($invitationReservation, $invitationPayment));
            } catch (\Throwable $exception) {
                report($exception);
                Log::warning('Guest account invitation could not be sent after check-in payment.', [
                    'reservation_id' => $invitationReservation->id,
                ]);
            }
        }

        return $processed;
    }

    private function markReceiptProcessing(): void
    {
        $this->updateReceipt([
            'status' => PaymentWebhookEvent::STATUS_PROCESSING,
            'attempts' => DB::raw('attempts + 1'),
            'processed_at' => null,
            'failed_at' => null,
        ]);
    }

    private function markReceiptRetrying(string $message): void
    {
        $this->updateReceipt([
            'status' => PaymentWebhookEvent::STATUS_RETRYING,
            'last_error' => $this->truncateError($message),
        ]);
    }

    private function markReceiptProcessed(): void
    {
        $this->updateReceipt([
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
            'failed_at' => null,
            'last_error' => null,
        ]);
    }

    private function markReceiptIgnored(): void
    {
        $this->updateReceipt([
            'status' => PaymentWebhookEvent::STATUS_IGNORED,
            'processed_at' => now(),
            'failed_at' => null,
            'last_error' => null,
        ]);
    }

    private function markReceiptFailed(string $message): void
    {
        $this->updateReceipt([
            'status' => PaymentWebhookEvent::STATUS_FAILED,
            'processed_at' => null,
            'failed_at' => now(),
            'last_error' => $this->truncateError($message),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateReceipt(array $attributes): void
    {
        if ($this->webhookEventRecordId === null) {
            return;
        }

        $updated = PaymentWebhookEvent::query()
            ->whereKey($this->webhookEventRecordId)
            ->update($attributes);

        if ($updated === 0 && ! PaymentWebhookEvent::whereKey($this->webhookEventRecordId)->exists()) {
            Log::warning('Payment webhook receipt was not found while updating job state', [
                'webhook_event_record_id' => $this->webhookEventRecordId,
            ]);
        }
    }

    private function truncateError(string $message): string
    {
        return Str::limit($message, 2000, '');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->markReceiptFailed($this->safeExceptionMessage($exception));
        app(SecurityMonitor::class)->webhook('permanent_job_failure');

        Log::error('Payment webhook processing failed permanently after retries', [
            'receipt_id' => $this->webhookEventRecordId,
            'error_type' => class_basename($exception),
        ]);

        // Optionally notify admins of failed webhook processing
        try {
            $reservationId = $this->reservationIdFromEvent() ?? 'unknown';

            NotificationHelper::notifyAllStaff(
                'Payment Webhook Processing Failed',
                "Failed to process PayMongo webhook for reservation ID {$reservationId}. Manual review required.",
                'danger',
                'payment',
                null,
                null,
                'reservations_edit'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send webhook failure notification', [
                'receipt_id' => $this->webhookEventRecordId,
                'error_type' => class_basename($e),
            ]);
        }
    }

    private function safeExceptionMessage(\Throwable $exception): string
    {
        return 'Webhook processing failed ('.class_basename($exception).').';
    }

    private function reservationIdFromEvent(): ?string
    {
        $event = PayMongoWebhookData::forQueue($this->webhookData);
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
        $metadata = match ($event['event_type'] ?? null) {
            'payment.paid' => $resource['metadata'] ?? [],
            'checkout_session.payment.paid' => array_merge(
                is_array($resource['metadata'] ?? null) ? $resource['metadata'] : [],
                is_array(data_get($resource, 'payment.metadata'))
                    ? data_get($resource, 'payment.metadata')
                    : [],
            ),
            default => [],
        };

        $reservationId = $metadata['reservation_id'] ?? null;

        return is_scalar($reservationId) ? (string) $reservationId : null;
    }
}
