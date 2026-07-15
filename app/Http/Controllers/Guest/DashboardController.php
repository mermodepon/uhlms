<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $account = Auth::guard('guest')->user();
        $reservations = $account->reservations()->with(['preferredRoomType', 'feedback'])->latest()->get();
        $claimable = $this->claimableReservations($account);

        $statCards = Reservation::guestDashboardCards();
        $stats = collect($statCards)->mapWithKeys(function (array $card, string $key) use ($reservations): array {
            $count = $key === 'upcoming'
                ? $reservations
                    ->where('check_in_date', '>=', now()->toDateString())
                    ->whereNotIn('status', ['checked_in', 'checked_out', 'cancelled', 'declined'])
                    ->count()
                : $reservations->whereIn('status', $card['statuses'] ?? [])->count();

            return [$key => $count];
        })->all();

        return view('guest.account.dashboard', compact('account', 'reservations', 'claimable', 'stats', 'statCards'));
    }

    public function reservations()
    {
        $account = Auth::guard('guest')->user();
        $reservations = $account->reservations()
            ->with(['preferredRoomType', 'roomRequests.roomType'])
            ->with('feedback')
            ->latest()
            ->get();

        return view('guest.account.reservations', compact('account', 'reservations'));
    }

    public function showReservation(Reservation $reservation)
    {
        $account = Auth::guard('guest')->user();
        abort_unless((int) $reservation->guest_account_id === (int) $account->id, 403);

        $reservation->load(['preferredRoomType', 'roomRequests.roomType', 'payments', 'roomAssignments.room.roomType', 'feedback']);
        $pendingCheckInBalancePayment = Setting::isOnlinePaymentsEnabled()
            ? $reservation->payments
                ->where('gateway', 'paymongo')
                ->where('is_deposit', false)
                ->where('gateway_status', 'pending')
                ->where('status', 'pending')
                ->first(fn ($payment) => data_get($payment->meta, 'source') === 'checkin_balance'
                    && filter_var(data_get($payment->gateway_metadata, 'checkout_url'), FILTER_VALIDATE_URL))
            : null;

        $canInitiateDepositPayment = $this->canInitiateDepositPayment($account, $reservation);

        return view('guest.account.reservation-detail', compact('account', 'reservation', 'pendingCheckInBalancePayment', 'canInitiateDepositPayment'));
    }

    public function startDepositPayment(Request $request, Reservation $reservation)
    {
        $account = Auth::guard('guest')->user();
        abort_unless($this->canInitiateDepositPayment($account, $reservation), 404);

        return redirect()->to($reservation->generatePaymentLink(false));
    }

    public function claim(Request $request)
    {
        $account = Auth::guard('guest')->user();

        if (! $account->hasVerifiedEmail()) {
            return back()->withErrors(['claim' => 'Please verify your email before claiming reservations.']);
        }

        $ids = $this->claimableReservations($account)->pluck('id')->all();

        if (! empty($ids)) {
            Reservation::whereIn('id', $ids)->update(['guest_account_id' => $account->id]);
        }

        return back()->with('success', count($ids).' reservation(s) linked to your account.');
    }

    private function claimableReservations($account)
    {
        if (! $account->hasVerifiedEmail()) {
            return collect();
        }

        return Reservation::query()
            ->whereNull('guest_account_id')
            ->whereRaw('LOWER(guest_email) = ?', [Str::lower($account->email)])
            ->latest()
            ->get();
    }

    private function canInitiateDepositPayment($account, Reservation $reservation): bool
    {
        if (! $account
            || ! $account->hasVerifiedEmail()
            || (int) $reservation->guest_account_id !== (int) $account->id
            || ! Setting::isOnlinePaymentsEnabled()
            || ! $reservation->isPaymentLinkValid()
            || ! $reservation->canAcceptGuestPayment()) {
            return false;
        }

        return ! $reservation->payments()
            ->where('gateway', 'paymongo')
            ->where('is_deposit', true)
            ->whereIn('gateway_status', ['paid', 'pending'])
            ->exists();
    }
}
