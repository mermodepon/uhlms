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
        if (! Setting::isOnlinePaymentsEnabled()) {
            abort(404, 'Online payments are not available.');
        }

        $reservation = Reservation::where('payment_link_token', $token)
            ->with('preferredRoomType')
            ->first();

        if (! $reservation) {
            abort(404, 'Payment link not found.');
        }

        if (! $reservation->isPaymentLinkValid()) {
            return view('guest.payment-expired', [
                'reservation' => $reservation,
            ]);
        }

        if (! $reservation->canAcceptGuestPayment()) {
            abort(404, 'Payment is only available after staff approval.');
        }

        $existingDeposit = ReservationPayment::where('reservation_id', $reservation->id)
            ->where('is_deposit', true)
            ->where('status', 'posted')
            ->where('gateway', 'paymongo')
            ->first();

        if ($existingDeposit) {
            return redirect()->route('guest.payment.success', ['reservation' => $reservation->reference_number])
                ->with('message', 'This reservation has already been paid.');
        }

        if (in_array($reservation->status, ['cancelled', 'declined', 'checked_out'], true)) {
            abort(404, 'Payment not available for this reservation.');
        }

        $gatewayService = app(PaymentGatewayService::class);

        return view('guest.payment', [
            'reservation' => $reservation,
            'depositAmount' => $reservation->calculateDepositAmount(),
            'fullAmount' => $reservation->calculateFullAmount(),
            'depositPercentage' => $reservation->deposit_percentage ?? Setting::getDefaultDepositPercentage(),
            'guestPaymentMethods' => $gatewayService->getGuestPaymentMethods(),
            'merchantPaymentMethods' => $gatewayService->getMerchantPaymentMethods(),
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
        if (! Setting::isOnlinePaymentsEnabled()) {
            abort(404, 'Online payments are not available.');
        }

        $reservation = Reservation::where('payment_link_token', $token)
            ->with('preferredRoomType')
            ->first();

        if (! $reservation || ! $reservation->isPaymentLinkValid()) {
            abort(404, 'Payment link not found or expired.');
        }

        if (! $reservation->canAcceptGuestPayment()) {
            abort(404, 'Payment is only available after staff approval.');
        }

        $request->validate([
            'accept_terms' => 'required|accepted',
            'payment_method' => 'required|in:gcash,paymaya,grab_pay,card,billease,qrph',
            'payment_type' => 'required|in:deposit,full',
        ]);

        try {
            $paymentType = $request->input('payment_type');
            $paymentAmount = $paymentType === 'full'
                ? $reservation->calculateFullAmount()
                : $reservation->calculateDepositAmount();

            if ($paymentAmount <= 0) {
                return back()->withErrors(['amount' => 'Unable to calculate payment amount. Please contact the homestay office.']);
            }

            if ($paymentAmount < 100) {
                return back()->withErrors([
                    'amount' => 'The calculated payment of P'.number_format($paymentAmount, 2).' is below the minimum amount for online payment (P100.00). Please pay at the front desk upon check-in.',
                ]);
            }

            $gatewayService = app(PaymentGatewayService::class);
            $selectedPaymentMethod = $request->input('payment_method');
            $checkoutPaymentMethods = [$selectedPaymentMethod];

            $checkoutSession = $gatewayService->createCheckoutSession(
                $reservation,
                $paymentAmount,
                $paymentType,
                $checkoutPaymentMethods,
                [
                    'success' => route('guest.payment.success', ['reservation' => $reservation->reference_number]),
                    'cancel' => route('guest.payment.failed', ['reservation' => $reservation->reference_number]),
                ]
            );

            $isDeposit = $paymentType === 'deposit';
            $payment = ReservationPayment::create([
                'reservation_id' => $reservation->id,
                'amount' => $paymentAmount,
                'payment_mode' => $selectedPaymentMethod,
                'gateway' => 'paymongo',
                'gateway_payment_id' => $checkoutSession['payment_intent_id'],
                'gateway_source_id' => $checkoutSession['checkout_session_id'],
                'gateway_status' => 'pending',
                'is_deposit' => $isDeposit,
                'status' => 'pending',
                'gateway_metadata' => [
                    'checkout_session_created_at' => now()->toIso8601String(),
                    'checkout_session_id' => $checkoutSession['checkout_session_id'] ?? null,
                    'checkout_payment_methods' => $checkoutSession['payment_method_types'] ?? $checkoutPaymentMethods,
                    'payment_type' => $paymentType,
                ],
                'meta' => [
                    'source' => 'guest_payment_page',
                    'payment_type' => $paymentType,
                ],
            ]);

            $paymentTypeLabel = $isDeposit ? 'deposit' : 'full payment';
            ReservationLog::record(
                $reservation,
                'payment_initiated',
                'Guest initiated online '.$paymentTypeLabel.' of P'.number_format($paymentAmount, 2).' via PayMongo Checkout.',
                [
                    'payment_id' => $payment->id,
                    'gateway_payment_id' => $checkoutSession['payment_intent_id'],
                    'checkout_session_id' => $checkoutSession['checkout_session_id'] ?? null,
                    'amount' => $paymentAmount,
                    'payment_method' => $selectedPaymentMethod,
                    'checkout_payment_methods' => $checkoutSession['payment_method_types'] ?? $checkoutPaymentMethods,
                    'payment_type' => $paymentType,
                    'is_deposit' => $isDeposit,
                    'ip_address' => $request->ip(),
                ]
            );

            if (blank($checkoutSession['checkout_url'] ?? null)) {
                throw new PaymentGatewayException('No checkout URL returned from payment gateway.');
            }

            return redirect($checkoutSession['checkout_url']);
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
