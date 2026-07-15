<?php

namespace App\Services;

use App\Models\GuestAccount;
use App\Models\Reservation;
use Illuminate\Support\Str;

class ReservationAccountLinker
{
    /**
     * Link one unclaimed reservation to its matching verified, active account.
     */
    public function link(Reservation $reservation): ?GuestAccount
    {
        if ($reservation->guest_account_id || blank($reservation->guest_email)) {
            return null;
        }

        $account = $this->matchingVerifiedAccount($reservation->guest_email);
        if (! $account) {
            return null;
        }

        $linked = Reservation::query()
            ->whereKey($reservation->id)
            ->whereNull('guest_account_id')
            ->update(['guest_account_id' => $account->id]);

        if (! $linked) {
            return null;
        }

        $reservation->guest_account_id = $account->id;

        return $account;
    }

    /**
     * Link all eligible, previously unclaimed reservations after email verification.
     */
    public function linkUnclaimedReservations(GuestAccount $account): int
    {
        if ($account->isDisabled() || ! $account->hasVerifiedEmail() || blank($account->email)) {
            return 0;
        }

        return Reservation::query()
            ->whereNull('guest_account_id')
            ->whereRaw('LOWER(guest_email) = ?', [$this->normalizeEmail($account->email)])
            ->update(['guest_account_id' => $account->id]);
    }

    /**
     * Whether an optional account invitation is appropriate for this reservation email.
     */
    public function shouldInviteToCreateAccount(Reservation $reservation): bool
    {
        return ! $reservation->guest_account_id
            && filled($reservation->guest_email)
            && ! GuestAccount::query()
                ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($reservation->guest_email)])
                ->exists();
    }

    private function matchingVerifiedAccount(string $email): ?GuestAccount
    {
        return GuestAccount::query()
            ->whereNull('disabled_at')
            ->whereNotNull('email_verified_at')
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->first();
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
