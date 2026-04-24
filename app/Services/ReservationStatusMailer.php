<?php

namespace App\Services;

use App\Mail\ReservationStatusMail;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReservationStatusMailer
{
    public function sendSubmitted(Reservation $reservation): void
    {
        $this->send($reservation, 'submitted');
    }

    public function sendStatusUpdate(Reservation $reservation, string $previousStatus): void
    {
        $this->send($reservation, 'status_changed', $previousStatus);
    }

    private function send(Reservation $reservation, string $context, ?string $previousStatus = null): void
    {
        if (blank($reservation->guest_email)) {
            return;
        }

        DB::afterCommit(function () use ($reservation, $context, $previousStatus) {
            $freshReservation = $reservation->fresh(['preferredRoomType']) ?? $reservation->loadMissing('preferredRoomType');

            try {
                Mail::to($freshReservation->guest_email)->send(
                    new ReservationStatusMail($freshReservation, $context, $previousStatus)
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to send reservation status email.', [
                    'reservation_id' => $reservation->id,
                    'reference_number' => $reservation->reference_number,
                    'guest_email' => $reservation->guest_email,
                    'context' => $context,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
