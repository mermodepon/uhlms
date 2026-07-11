<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
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

        $stats = [
            'upcoming' => $reservations->where('check_in_date', '>=', now()->toDateString())->count(),
            'pending' => $reservations->where('status', 'pending')->count(),
            'active' => $reservations->whereIn('status', ['approved', 'confirmed', 'checked_in'])->count(),
            'completed' => $reservations->where('status', 'checked_out')->count(),
        ];

        return view('guest.account.dashboard', compact('account', 'reservations', 'claimable', 'stats'));
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

        return view('guest.account.reservation-detail', compact('account', 'reservation'));
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
}
