<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Setting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class QrCodeController extends Controller
{
    public function accountCheckInBalanceQr(Request $request, Reservation $reservation)
    {
        $checkoutUrl = $this->accountCheckInBalanceCheckoutUrl($request, $reservation);

        return $this->svgResponse($checkoutUrl);
    }

    public function accountCheckInBalanceCheckout(Request $request, Reservation $reservation)
    {
        return redirect()->away($this->accountCheckInBalanceCheckoutUrl($request, $reservation));
    }

    public function payment(string $token)
    {
        if (! Setting::isOnlinePaymentsEnabled()) {
            abort(404);
        }

        $reservation = Reservation::where('payment_link_token', $token)->firstOrFail();

        if (! $reservation->isPaymentLinkValid() || ! $reservation->canAcceptGuestPayment()) {
            abort(404);
        }

        $paymentPath = $reservation->generatePaymentLink(false);
        if ($paymentPath === null) {
            abort(404);
        }

        return $this->svgResponse(url($paymentPath));
    }

    public function encrypted(Request $request)
    {
        if (! auth()->check()) {
            abort(403);
        }

        try {
            $data = Crypt::decryptString((string) $request->query('payload', ''));
        } catch (DecryptException) {
            abort(404);
        }

        if (! filter_var($data, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        $scheme = parse_url($data, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            abort(404);
        }

        return $this->svgResponse($data);
    }

    private function svgResponse(string $data)
    {
        $result = Builder::create()
            ->writer(new SvgWriter())
            ->writerOptions([SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true])
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(320)
            ->margin(12)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->foregroundColor(new Color(0, 73, 30))
            ->backgroundColor(new Color(255, 255, 255))
            ->build();

        return response($result->getString(), 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function accountCheckInBalanceCheckoutUrl(Request $request, Reservation $reservation): string
    {
        $account = $request->user('guest');

        abort_unless(
            $account
                && $account->hasVerifiedEmail()
                && (int) $reservation->guest_account_id === (int) $account->id,
            403,
        );

        if (! Setting::isOnlinePaymentsEnabled()) {
            abort(404);
        }

        $payment = $reservation->payments()
            ->where('gateway', 'paymongo')
            ->where('is_deposit', false)
            ->where('gateway_status', 'pending')
            ->where('status', 'pending')
            ->where('meta->source', 'checkin_balance')
            ->latest('id')
            ->first();

        $checkoutUrl = $payment ? data_get($payment->gateway_metadata, 'checkout_url') : null;

        if (! is_string($checkoutUrl) || ! filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        $scheme = parse_url($checkoutUrl, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            abort(404);
        }

        return $checkoutUrl;
    }
}
