<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\Reservation;
use App\Models\StayLog;
use App\Models\User;

class ReservationObserver
{
    public function created(Reservation $reservation): void
    {
        // Notify all admins/staff of new reservation
        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'New Reservation',
                "Reservation #{$reservation->reference_number} from {$reservation->guest_name} has been created.",
                'info',
                'reservation',
                '/admin/reservations/' . $reservation->id,
                auth()->id()
            );
        }
    }

    public function updated(Reservation $reservation): void
    {
        $changes = $reservation->getChanges();
        
        // Only notify if status changed
        if (array_key_exists('status', $changes)) {
            $newStatus = $changes['status'];
            $oldStatus = $reservation->getOriginal('status');

            // Close any open stay logs and free rooms if reservation is cancelled, declined, or checked out
            if (in_array($newStatus, ['cancelled', 'declined', 'checked_out'])) {
                $openLogs = StayLog::where('reservation_id', $reservation->id)
                    ->whereNotNull('checked_in_at')
                    ->whereNull('checked_out_at')
                    ->get();

                foreach ($openLogs as $log) {
                    $log->update([
                        'checked_out_at' => now(),
                        'checked_out_by' => auth()->id(),
                        'remarks' => 'Auto-closed: reservation status changed to ' . $newStatus,
                    ]);
                    // Free the room
                    if ($log->room) {
                        $log->room->update(['status' => 'available']);
                    }
                }

                // Also free any rooms on assignments directly (covers cases with no stay log)
                foreach ($reservation->roomAssignments as $assignment) {
                    if ($assignment->room && $assignment->room->status === 'occupied') {
                        $assignment->room->update(['status' => 'available']);
                    }
                }
            }

            // Notify all admins of status change
            $staff = User::whereIn('role', ['admin', 'staff'])->get();
            foreach ($staff as $user) {
                Notification::createNotification(
                    $user,
                    'Reservation Status Updated',
                    "Reservation #{$reservation->reference_number} status changed from {$oldStatus} to {$newStatus}.",
                    $this->getStatusNotificationType($newStatus),
                    'reservation',
                    '/admin/reservations/' . $reservation->id,
                    auth()->id()
                );
            }
        }
    }

    public function deleted(Reservation $reservation): void
    {
        // Close any open stay logs when reservation is deleted
        StayLog::where('reservation_id', $reservation->id)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->update([
                'checked_out_at' => now(),
                'checked_out_by' => auth()->id(),
                'remarks' => 'Auto-closed: reservation was deleted',
            ]);

        $staff = User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staff as $user) {
            Notification::createNotification(
                $user,
                'Reservation Deleted',
                "Reservation #{$reservation->reference_number} from {$reservation->guest_name} has been deleted.",
                'warning',
                'reservation',
                null,
                auth()->id()
            );
        }
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
