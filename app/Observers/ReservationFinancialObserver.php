<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationPayment;

class ReservationFinancialObserver
{
    public function created(ReservationCharge|ReservationPayment $entry): void
    {
        $this->refreshReservation($entry);
    }

    public function updated(ReservationCharge|ReservationPayment $entry): void
    {
        $this->refreshReservation($entry);
    }

    public function deleted(ReservationCharge|ReservationPayment $entry): void
    {
        $this->refreshReservation($entry);
    }

    private function refreshReservation(ReservationCharge|ReservationPayment $entry): void
    {
        Reservation::query()->find($entry->reservation_id)?->refreshFinancialSummary();
    }
}