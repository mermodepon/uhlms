<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayException;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\ReservationPayment;
use App\Models\Setting;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GuestPaymentController extends Controller
{
    /**
     * Show the payment page for a reservation.
     *
     * @param  string  $token  Payment link token
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showPaymentPage(string $token)
    {
        // Feature toggle check
        if (! Setting::isOnlinePaymentsEnabled()) {
            abort(404, 'Online payments are not available.');
        }

        // Find reservation by token
        $reservation = Reservation::where('payment_link_token', $token)
            ->with('preferredRoomType')
            ->first();

        if (! $reservation) {
            abort(404, 'Payment link not found.');
        }

        // Check if token is still valid
        if (! $reservation->isPaymentLinkValid()) {
            return view('guest.payment-expired', [
                'reservation' => $reservation,
            ]);
        }

        // Check if already paid
        $existingDeposit = ReservationPayment::where('reservation_id', $reservation->id)
            ->where('is_deposit', true)
            ->where('status', 'posted')
            ->where('gateway', 'paymongo')
            ->first();

        if ($existingDeposit) {
            return redirect()->route('guest.payment.success', ['reservation' => $reservation->reference_number])
                ->with('message', 'This reservation has already been paid.');
        }

        // Check if reservation status allows payment
        if (in_array($reservation->status, ['cancelled', 'declined', 'checked_out'])) {
            abort(404, 'Payment not available for this reservation.');
        }

        // Calculate deposit and full amounts
        $depositAmount = $reservation->calculateDepositAmount();
        $fullAmount = $reservation->calculateFullAmount();

        return view('guest.payment', [
            'reservation' => $reservation,
            'depositAmount' => $depositAmount,
            'fullAmount' => $fullAmount,
            'depositPercentage' => $reservation->deposit_percentage ?? Setting::getDefaultDepositPercentage(),
        ]);
    }

    /**
     * Initialize payment and redirect to PayMongo checkout.
     *
     * @param  string  $token  Payment link token
     * @return \Illuminate\Http\RedirectResponse
     */
    public function initializePayment(string $token, Request $request)
    {
        // Feature toggle check
        if (! Setting::isOnlinePaymentsEnabled()) {
            abort(404, 'Online payments are not available.');
        }

        // Find reservation by token
        $reservation = Reservation::where('payment_link_token', $token)
            ->with('preferredRoomType')
            ->first();

        if (! $reservation || ! $reservation->isPaymentLinkValid()) {
            abort(404, 'Payment link not found or expired.');
        }

        // Validate terms acceptance and payment type
        $request->validate([
            'accept_terms' => 'required|accepted',
            'payment_method' => 'required|in:gcash,paymaya,grab_pay',
            'payment_type' => 'required|in:deposit,full',
        ]);

        try {
            // Determine payment amount based on type
            $paymentType = $request->input('payment_type');
            $paymentAmount = $paymentType === 'full'
                ? $reservation->calculateFullAmount()
                : $reservation->calculateDepositAmount();

            $isDeposit = $paymentType === 'deposit';

            // Validate payment amount
            if ($paymentAmount <= 0) {
                return back()->withErrors(['amount' => 'Unable to calculate payment amount. Please contact the homestay office.']);
            }

            // Validate PayMongo minimum (₱100.00)
            if ($paymentAmount < 100) {
                return back()->withErrors([
                    'amount' => 'The calculated payment of ₱'.number_format($paymentAmount, 2).' is below the minimum amount for online payment (₱100.00). Please pay at the front desk upon check-in.',
                ]);
            }

            // Create payment intent via PayMongo
            $gatewayService = app(PaymentGatewayService::class);
            $paymentIntent = $gatewayService->createPaymentIntent($reservation, $paymentAmount, $paymentType);

            // Create pending payment record
            $payment = ReservationPayment::create([
                'reservation_id' => $reservation->id,
                'amount' => $paymentAmount,
                'payment_mode' => $request->input('payment_method'),
                'gateway' => 'paymongo',
                'gateway_payment_id' => $paymentIntent['payment_id'],
                'gateway_status' => 'pending',
                'is_deposit' => $isDeposit,  // Set based on payment type
                'status' => 'pending', // Will be updated to 'posted' when webhook arrives
                'gateway_metadata' => [
                    'payment_intent_created_at' => now()->toIso8601String(),
                    'client_key' => $paymentIntent['client_key'] ?? null,
                    'payment_type' => $paymentType,  // Track payment type
                ],
                'meta' => [
                    'source' => 'guest_payment_page',
                    'payment_type' => $paymentType,
                ],
            ]);

            // Log payment initiation
            $paymentTypeLabel = $isDeposit ? 'deposit' : 'full payment';
            ReservationLog::record(
                $reservation,
                'payment_initiated',
                "Guest initiated online {$paymentTypeLabel} of ₱".number_format($paymentAmount, 2)." via {$request->input('payment_method')}.",
                [
                    'payment_id' => $payment->id,
                    'gateway_payment_id' => $paymentIntent['payment_id'],
                    'amount' => $paymentAmount,
                    'payment_method' => $request->input('payment_method'),
                    'payment_type' => $paymentType,
                    'is_deposit' => $isDeposit,
                    'ip_address' => $request->ip(),
                ]
            );

            // Attach payment method and get checkout URL
            $returnUrls = [
                'success' => route('guest.payment.success', ['reservation' => $reservation->reference_number]),
                'failed' => route('guest.payment.failed', ['reservation' => $reservation->reference_number]),
            ];

            $checkoutData = $gatewayService->attachPaymentMethod(
                $paymentIntent['payment_id'],
                $request->input('payment_method'),
                $returnUrls
            );

            // Update payment with source ID
            $payment->update([
                'gateway_source_id' => $checkoutData['source_id'],
            ]);

            // Redirect to PayMongo checkout
            return redirect($checkoutData['checkout_url']);
        } catch (PaymentGatewayException $e) {
            Log::error('Payment initialization failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['payment' => 'Failed to initialize payment: '.$e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('Unexpected error during payment initialization', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['payment' => 'An unexpected error occurred. Please try again.']);
        }
    }

    /**
     * Show payment success page.
     *
     * @return \Illuminate\View\View
     */
    public function paymentSuccess(Request $request)
    {
        $reservationRef = $request->query('reservation');
        $reservation = null;

        if ($reservationRef) {
            $reservation = Reservation::where('reference_number', $reservationRef)->first();
        }

        return view('guest.payment-success', [
            'reservation' => $reservation,
            'message' => $request->session()->get('message'),
        ]);
    }

    /**
     * Show payment failure page.
     *
     * @return \Illuminate\View\View
     */
    public function paymentFailed(Request $request)
    {
        $reservationRef = $request->query('reservation');
        $reservation = null;

        if ($reservationRef) {
            $reservation = Reservation::where('reference_number', $reservationRef)->first();
        }

        return view('guest.payment-failed', [
            'reservation' => $reservation,
        ]);
    }
}
