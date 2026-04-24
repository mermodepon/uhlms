<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\RoomAssignment;
use App\Notifications\NotificationHelper;
use App\Services\ReservationStatusMailer;
use Illuminate\Support\Str;

class ReservationObserver
{
    /**
     * Handle the Reservation "creating" event.
     * Generate payment link token before saving to database.
     */
    public function creating(Reservation $reservation): void
    {
        // Auto-generate payment link token and expiry (48 hours from now)
        if (empty($reservation->payment_link_token)) {
            $reservation->payment_link_token = (string) Str::uuid();
            $reservation->payment_link_expires_at = now()->addHours(48);
        }
    }

    public function created(Reservation $reservation): void
    {
        ReservationLog::record(
            $reservation,
            'reservation_created',
            "Reservation #{$reservation->reference_number} created for {$reservation->guest_name}."
        );

        // Notify all admins/staff of new reservation
        NotificationHelper::notifyAllStaff(
            'New Reservation',
            "Reservation #{$reservation->reference_number} from {$reservation->guest_name} has been created.",
            'info',
            'reservation',
            url('/admin/reservations?tableSearch='.urlencode($reservation->reference_number)),
            auth()->id(),
            'reservations_view'
        );

        app(ReservationStatusMailer::class)->sendSubmitted($reservation);
    }

    public function updated(Reservation $reservation): void
    {
        $changes = $reservation->getChanges();

        // Sync guest names to any existing assignments if the reservation name fields changed
        if (array_key_exists('guest_first_name', $changes) || array_key_exists('guest_last_name', $changes) || array_key_exists('guest_middle_initial', $changes)) {
            RoomAssignment::where('reservation_id', $reservation->id)
                ->update([
                    'guest_first_name' => $reservation->guest_first_name,
                    'guest_last_name' => $reservation->guest_last_name,
                    'guest_middle_initial' => $reservation->guest_middle_initial,
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
                        'remarks' => 'Auto-closed: reservation status changed to '.$newStatus,
                    ]);
                    // Note: RoomAssignmentObserver will automatically update the room status
                }
            }

            // Log meaningful status transitions
            $logEvent = match (true) {
                $oldStatus === 'pending' && $newStatus === 'approved' => 'reservation_approved',
                $oldStatus === 'approved' && $newStatus === 'confirmed' => 'reservation_confirmed',
                $newStatus === 'declined' => 'reservation_declined',
                $newStatus === 'cancelled' => 'reservation_cancelled',
                $oldStatus === 'checked_in' && $newStatus === 'checked_out' => 'reservation_checked_out',
                $oldStatus === 'pending_payment' && $newStatus === 'approved' => 'checkin_hold_released',
                $oldStatus === 'pending_payment' && $newStatus === 'confirmed' => 'checkin_hold_released',
                default => null,
            };

            if ($logEvent) {
                $description = match ($logEvent) {
                    'reservation_approved' => "Reservation #{$reservation->reference_number} approved.",
                    'reservation_confirmed' => "Reservation #{$reservation->reference_number} confirmed with rooms assigned.",
                    'reservation_declined' => "Reservation #{$reservation->reference_number} declined.",
                    'reservation_cancelled' => "Reservation #{$reservation->reference_number} cancelled."
                        .($reservation->admin_notes ? " Reason: {$reservation->admin_notes}" : ''),
                    'reservation_checked_out' => "Reservation #{$reservation->reference_number} checked out.",
                    'checkin_hold_released' => "Payment hold released. Reservation #{$reservation->reference_number} returned to ".($newStatus === 'confirmed' ? 'confirmed' : 'approved').".",
                    default => "Status changed from {$oldStatus} to {$newStatus}.",
                };

                ReservationLog::record(
                    $reservation,
                    $logEvent,
                    $description,
                    ['from' => $oldStatus, 'to' => $newStatus]
                );
            }

            // Notify all admins of status change
            NotificationHelper::notifyAllStaff(
                'Reservation Status Updated',
                "Reservation #{$reservation->reference_number} status changed from {$oldStatus} to {$newStatus}.",
                $this->getStatusNotificationType($newStatus),
                'reservation',
                url('/admin/reservations?tableSearch='.urlencode($reservation->reference_number)),
                auth()->id(),
                'reservations_view'
            );

            app(ReservationStatusMailer::class)->sendStatusUpdate($reservation, $oldStatus);
        }
    }

    public function deleting(Reservation $reservation): void
    {
        // Capture room IDs *before* the DB cascade removes the assignments.
        // We need these in deleted() to recalculate statuses after the cascade.
        $reservation->_affectedRoomIds = RoomAssignment::where('reservation_id', $reservation->id)
            ->pluck('room_id')
            ->unique()
            ->values()
            ->all();
    }

    public function deleted(Reservation $reservation): void
    {
        // After the cascade has removed the room_assignments, recalculate the
        // status of every room that was used by this reservation.
        foreach ($reservation->_affectedRoomIds ?? [] as $roomId) {
            $room = \App\Models\Room::with('roomType')->find($roomId);
            $room?->recalculateStatus();
        }

        NotificationHelper::notifyAllStaff(
            'Reservation Deleted',
            "Reservation #{$reservation->reference_number} from {$reservation->guest_name} has been deleted.",
            'warning',
            'reservation',
            null,
            auth()->id(),
            'reservations_view'
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


}
