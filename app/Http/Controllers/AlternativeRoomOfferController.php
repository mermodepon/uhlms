<?php

namespace App\Http\Controllers;

use App\Models\ReservationAlternativeOffer;
use App\Services\AlternativeRoomOfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AlternativeRoomOfferController extends Controller
{
    public function show(Request $request, ReservationAlternativeOffer $offer)
    {
        abort_unless($request->hasValidSignature(false), 403);
        $offer->load(['reservation', 'requestLine.roomType', 'offeredRoomType']);
        app(AlternativeRoomOfferService::class)->expireIfNeeded($offer);
        $offer->refresh();

        $acceptUrl = URL::temporarySignedRoute('guest.alternative-offers.accept', $offer->expires_at, ['offer' => $offer->id], false);
        $declineUrl = URL::temporarySignedRoute('guest.alternative-offers.decline', $offer->expires_at, ['offer' => $offer->id], false);

        return view('guest.alternative-room-offer', compact('offer', 'acceptUrl', 'declineUrl'));
    }

    public function accept(Request $request, ReservationAlternativeOffer $offer): RedirectResponse
    {
        abort_unless($request->hasValidSignature(false), 403);
        $reservation = app(AlternativeRoomOfferService::class)->accept($offer);

        try {
            \Illuminate\Support\Facades\Mail::to($reservation->guest_email)->send(new \App\Mail\SendPaymentLinkMail($reservation));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()->route('guest.payment.show', ['token' => $reservation->payment_link_token], false)
            ->with('success', 'Alternative room offer accepted. Complete your payment to secure the reservation.');
    }

    public function decline(Request $request, ReservationAlternativeOffer $offer): RedirectResponse
    {
        abort_unless($request->hasValidSignature(false), 403);
        app(AlternativeRoomOfferService::class)->decline($offer);

        $showUrl = URL::temporarySignedRoute('guest.alternative-offers.show', now()->addMinutes(5), ['offer' => $offer->id], false);

        return redirect()->to($showUrl)
            ->with('success', 'The alternative room offer was declined. Our staff will contact you with the next options.');
    }
}
