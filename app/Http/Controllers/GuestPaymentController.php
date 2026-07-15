<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayException;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\ReservationPayment;
use App\Models\Setting;
use App\Services\PaymentGatewayService;
use App\Support\PayMongoPaymentMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                'trackingUrl' => $reservation->generateGuestTrackingUrl(),
                'accountReservationUrl' => $this->accountReservationUrl($reservation),
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
            return redirect()->route('guest.payment.success', ['token' => $reservation->payment_link_token])
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
                    'success' => $this->requestAbsoluteUrl(route('guest.payment.success', ['token' => $reservation->payment_link_token], false)),
                    'cancel' => $this->requestAbsoluteUrl(route('guest.payment.failed', ['token' => $reservation->payment_link_token], false)),
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
                'gateway_metadata' => PayMongoPaymentMetadata::sanitize([
                    'checkout_session_created_at' => now()->toIso8601String(),
                    'checkout_session_id' => $checkoutSession['checkout_session_id'] ?? null,
                    'checkout_payment_methods' => $checkoutSession['payment_method_types'] ?? $checkoutPaymentMethods,
                    'payment_type' => $paymentType,
                ], 'pending'),
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
                'error_type' => class_basename($e),
            ]);

            return back()->withErrors(['payment' => 'Failed to initialize payment: '.$e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('Unexpected error during payment initialization', [
                'reservation_id' => $reservation->id,
                'error_type' => class_basename($e),
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
        $reservation = $this->resolvePaymentResultReservation($request);

        return view('guest.payment-success', [
            'reservation' => $reservation,
            'message' => $request->session()->get('message'),
            'trackingUrl' => $reservation?->generateGuestTrackingUrl(),
            'accountReservationUrl' => $this->accountReservationUrl($reservation),
        ]);
    }

    /**
     * Show payment failure page.
     *
     * @return \Illuminate\View\View
     */
    public function paymentFailed(Request $request)
    {
        $reservation = $this->resolvePaymentResultReservation($request);

        return view('guest.payment-failed', [
            'reservation' => $reservation,
            'accountReservationUrl' => $this->accountReservationUrl($reservation),
        ]);
    }

    /**
     * Show the guest-safe return page for a check-in balance checkout.
     *
     * Check-in balance checkout is initiated by staff, but its return URL must
     * never send a guest to the authenticated staff workflow.
     */
    public function checkInBalancePaymentResult(Request $request, string $token)
    {
        abort_unless(Str::isUuid($token), 404);

        $payment = ReservationPayment::query()
            ->where('gateway', 'paymongo')
            ->where('meta->source', 'checkin_balance')
            ->where(function ($query) use ($token): void {
                $query->where('meta->guest_result_token', $token)
                    ->orWhere('gateway_metadata->guest_result_token', $token);
            })
            ->with('reservation')
            ->firstOrFail();

        return view('guest.check-in-balance-payment-result', [
            'payment' => $payment,
            'reservation' => $payment->reservation,
            'cancelled' => $request->boolean('cancelled'),
            'accountReservationUrl' => $this->accountReservationUrl($payment->reservation),
        ]);
    }

    /**
     * Resolve a reservation for a payment result without exposing predictable references.
     */
    private function resolvePaymentResultReservation(Request $request): ?Reservation
    {
        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            return null;
        }

        $reservation = Reservation::where('payment_link_token', $token)->first();

        return $reservation?->isPaymentLinkValid() ? $reservation : null;
    }

    private function accountReservationUrl(?Reservation $reservation): ?string
    {
        $account = auth('guest')->user();

        if (! $reservation || ! $account || (int) $reservation->guest_account_id !== (int) $account->id) {
            return null;
        }

        return route('guest.account.reservations.show', $reservation, false);
    }

    private function requestAbsoluteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
