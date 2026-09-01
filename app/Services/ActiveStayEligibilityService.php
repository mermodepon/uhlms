<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Carbon;

class ActiveStayEligibilityService
{
    /**
     * Reservations that represent an active or already-confirmed stay.
     *
     * @var list<string>
     */
    private const CONFLICT_STATUSES = ['approved', 'confirmed', 'checked_in'];

    public function findConflictForReservation(Reservation $reservation): ?Reservation
    {
        return $this->findConflict(
            guestAccountId: $reservation->guest_account_id,
            email: $reservation->guest_email,
            phone: $reservation->guest_phone,
            checkIn: $reservation->check_in_date,
            checkOut: $reservation->check_out_date,
            ignoreReservationId: $reservation->id,
        );
    }

    public function findConflictForCheckIn(Reservation $reservation, ?string $idNumber = null): ?Reservation
    {
        if ($reservation->guest_account_id === null && filled($idNumber)) {
            $checkInDate = Carbon::parse($reservation->check_in_date)->toDateString();
            $checkOutDate = Carbon::parse($reservation->check_out_date)->toDateString();

            $conflict = Reservation::query()
                ->where('status', 'checked_in')
                ->whereHas('roomAssignments', fn ($query) => $query
                    ->where('status', 'checked_in')
                    ->whereRaw('UPPER(TRIM(id_number)) = ?', [strtoupper(trim($idNumber))]))
                ->whereKeyNot($reservation->id)
                ->orderBy('check_in_date')
                ->orderBy('id')
                ->first();

            if ($conflict) {
                return $conflict;
            }

            $conflict = Reservation::query()
                ->whereIn('status', self::CONFLICT_STATUSES)
                ->whereDate('check_in_date', '<', $checkOutDate)
                ->whereDate('check_out_date', '>', $checkInDate)
                ->whereKeyNot($reservation->id)
                ->whereHas('roomAssignments', fn ($query) => $query
                    ->whereRaw('UPPER(TRIM(id_number)) = ?', [strtoupper(trim($idNumber))]))
                ->orderBy('check_in_date')
                ->orderBy('id')
                ->first();

            if ($conflict) {
                return $conflict;
            }
        }

        return $this->findConflictForReservation($reservation);
    }

    public function findConflictForIdentity(
        ?int $guestAccountId,
        ?string $email,
        ?string $phone,
        Carbon|string $checkIn,
        Carbon|string $checkOut,
        ?int $ignoreReservationId = null,
    ): ?Reservation {
        return $this->findConflict(
            guestAccountId: $guestAccountId,
            email: $email,
            phone: $phone,
            checkIn: $checkIn,
            checkOut: $checkOut,
            ignoreReservationId: $ignoreReservationId,
        );
    }

    public function checkInConflictMessage(Reservation $conflict): string
    {
        return "Guest has another active reservation ({$conflict->reference_number}). Check out or cancel that reservation before checking in.";
    }

    public function reservationConflictMessage(Reservation $conflict): string
    {
        return "You already have another active reservation ({$conflict->reference_number}). Complete or cancel it before submitting another stay.";
    }

    /**
     * Hotel stays use a half-open interval: checkout day can be another guest's arrival day.
     */
    private function findConflict(
        ?int $guestAccountId,
        ?string $email,
        ?string $phone,
        Carbon|string $checkIn,
        Carbon|string $checkOut,
        ?int $ignoreReservationId,
    ): ?Reservation {
        $checkInDate = Carbon::parse($checkIn)->toDateString();
        $checkOutDate = Carbon::parse($checkOut)->toDateString();

        if ($checkInDate >= $checkOutDate) {
            return null;
        }

        $openCheckInConflict = Reservation::query()
            ->where('status', 'checked_in')
            ->whereHas('roomAssignments', fn ($query) => $query->where('status', 'checked_in'))
            ->when($ignoreReservationId !== null, fn ($query) => $query->whereKeyNot($ignoreReservationId))
            ->where($this->identityConstraint($guestAccountId, $email, $phone))
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->first();

        if ($openCheckInConflict) {
            return $openCheckInConflict;
        }

        return Reservation::query()
            ->whereIn('status', self::CONFLICT_STATUSES)
            ->whereDate('check_in_date', '<', $checkOutDate)
            ->whereDate('check_out_date', '>', $checkInDate)
            ->when($ignoreReservationId !== null, fn ($query) => $query->whereKeyNot($ignoreReservationId))
            ->where($this->identityConstraint($guestAccountId, $email, $phone))
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->first();
    }

    private function identityConstraint(?int $guestAccountId, ?string $email, ?string $phone): \Closure
    {
        return function ($query) use ($guestAccountId, $email, $phone): void {
            if ($guestAccountId !== null) {
                $query->where('guest_account_id', $guestAccountId);

                return;
            }

            $query->where(function ($identityQuery) use ($email, $phone): void {
                if (filled($email)) {
                    $identityQuery->whereRaw('LOWER(guest_email) = ?', [mb_strtolower(trim($email))]);

                    return;
                }

                $phoneVariants = $this->phoneVariants($phone);
                $phoneVariants === []
                    ? $identityQuery->whereRaw('1 = 0')
                    : $identityQuery->whereIn('guest_phone', $phoneVariants);
            });
        };
    }

    /**
     * @return list<string>
     */
    private function phoneVariants(?string $phone): array
    {
        if (! filled($phone)) {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return [];
        }

        $local = match (true) {
            str_starts_with($digits, '09') => $digits,
            str_starts_with($digits, '639') => '0'.substr($digits, 2),
            str_starts_with($digits, '9') => '0'.$digits,
            default => $digits,
        };

        $international = str_starts_with($local, '0') ? '63'.substr($local, 1) : $local;

        return collect([$phone, $digits, $local, '+'.$international, $international])
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
