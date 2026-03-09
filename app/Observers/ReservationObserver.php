<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\Reservation;
use App\Models\RoomAssignment;
use App\Models\User;
use App\Notifications\NotificationHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ReservationObserver
{
    public function created(Reservation $reservation): void
    {
        $this->clearReservationCalendarCache($reservation);

        // Notify all admins/staff of new reservation
        NotificationHelper::notifyAllStaff(
            'New Reservation',
            "Reservation #{$reservation->reference_number} from {$reservation->guest_name} has been created.",
            'info',
            'reservation',
            '/admin/reservations/' . $reservation->id,
            auth()->id()
        );
    }

    public function updated(Reservation $reservation): void
    {
        $changes = $reservation->getChanges();
        $this->clearReservationCalendarCache($reservation);
        
        // Sync guest names to any existing assignments if the reservation name fields changed
        if (array_key_exists('guest_first_name', $changes) || array_key_exists('guest_last_name', $changes) || array_key_exists('guest_middle_initial', $changes)) {
            RoomAssignment::where('reservation_id', $reservation->id)
                ->update([
                    'guest_first_name'    => $reservation->guest_first_name,
                    'guest_last_name'     => $reservation->guest_last_name,
                    'guest_middle_initial'=> $reservation->guest_middle_initial,
                ]);
        }

        // Only notify if status changed
        if (array_key_exists('status', $changes)) {
            $newStatus = $changes['status'];
            $oldStatus = $reservation->getOriginal('status');

            // Close any open assignments if reservation is cancelled or declined
            // (checked_out status is handled by explicit checkout action in ReservationResource)
            if (in_array($newStatus, ['cancelled', 'declined'])) {
                $openAssignments = RoomAssignment::where('reservation_id', $reservation->id)
                    ->whereNotNull('checked_in_at')
                    ->whereNull('checked_out_at')
                    ->get();

                foreach ($openAssignments as $assign) {
                    $assign->update([
                        'checked_out_at' => now(),
                        'checked_out_by' => auth()->id(),
                        'remarks' => 'Auto-closed: reservation status changed to ' . $newStatus,
                    ]);
                    // Note: When assignment is updated with checked_out_at, the RoomAssignmentObserver
                    // will automatically free the bed and update the room status via BedObserver
                }
            }

            // Notify all admins of status change
            NotificationHelper::notifyAllStaff(
                'Reservation Status Updated',
                "Reservation #{$reservation->reference_number} status changed from {$oldStatus} to {$newStatus}.",
                $this->getStatusNotificationType($newStatus),
                'reservation',
                '/admin/reservations/' . $reservation->id,
                auth()->id()
            );
        }
    }

    public function deleted(Reservation $reservation): void
    {
        $this->clearReservationCalendarCache($reservation);

        // Close any open assignments when reservation is deleted
        RoomAssignment::where('reservation_id', $reservation->id)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->update([
                'checked_out_at' => now(),
                'checked_out_by' => auth()->id(),
                'remarks' => 'Auto-closed: reservation was deleted',
            ]);

        NotificationHelper::notifyAllStaff(
            'Reservation Deleted',
            "Reservation #{$reservation->reference_number} from {$reservation->guest_name} has been deleted.",
            'warning',
            'reservation',
            null,
            auth()->id()
        );
    }

    private function getStatusNotificationType(string $status): string
    {
        return match ($status) {
            'approved' => 'success',
            'declined', 'cancelled' => 'danger',
            'checked_in', 'checked_out' => 'info',
            default => 'info',
        };
    }

    private function clearReservationCalendarCache(Reservation $reservation): void
    {
        $this->forgetMonthRange(
            $this->toCarbonDate($reservation->check_in_date),
            $this->toCarbonDate($reservation->check_out_date)
        );

        // On update, clear cache for old dates too in case month/date range changed.
        $this->forgetMonthRange(
            $this->toCarbonDate($reservation->getOriginal('check_in_date')),
            $this->toCarbonDate($reservation->getOriginal('check_out_date'))
        );
    }

    private function forgetMonthRange(?Carbon $checkIn, ?Carbon $checkOut): void
    {
        if (! $checkIn || ! $checkOut) {
            return;
        }

        if ($checkOut->lt($checkIn)) {
            [$checkIn, $checkOut] = [$checkOut, $checkIn];
        }

        $currentMonth = $checkIn->copy()->startOfMonth();
        $lastMonth = $checkOut->copy()->startOfMonth();

        while ($currentMonth->lte($lastMonth)) {
            Cache::forget("dashboard.calendar.{$currentMonth->year}.{$currentMonth->month}");
            $currentMonth->addMonthNoOverflow();
        }
    }

    private function toCarbonDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->copy()->startOfDay()
            : Carbon::parse($value)->startOfDay();
    }
}
