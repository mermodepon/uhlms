<?php

namespace App\Services;

use App\Mail\SendPaymentLinkMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReservationWorkflowService
{
    /**
     * Approve a pending reservation and optionally place advance room holds.
     *
     * @param  array{admin_notes?: ?string, assigned_room_ids?: array<int, int>, assigned_room_ids_by_type?: array<int|string, array<int, int>>}  $data
     * @return array{reservation: Reservation, room_count: int, hold_error: ?string}
     */
    public function approve(Reservation $reservation, array $data = []): array
    {
        if ($reservation->status !== 'pending') {
            throw new \RuntimeException('Only pending reservations can be approved.');
        }

        $reservation->update([
            'status' => 'approved',
            'approved_at' => now(),
            'admin_notes' => $data['admin_notes'] ?? $reservation->admin_notes,
            'payment_link_token' => null,
            'payment_link_expires_at' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $roomIdsByType = $this->normalizeAssignedRoomsByType($reservation, $data);
        $roomIds = collect($roomIdsByType)->flatten()->filter()->values()->all();

        if (empty($roomIds)) {
            ReservationLog::record(
                $reservation,
                'approved_without_rooms',
                'Approved without room assignment. Rooms will be assigned during check-in.'
            );

            return [
                'reservation' => $reservation->fresh(),
                'room_count' => 0,
                'hold_error' => null,
            ];
        }

        try {
            $result = count($roomIdsByType) > 1 || ! empty($data['assigned_room_ids_by_type'])
                ? app(RoomHoldService::class)->createAdvanceHoldsByRoomType($reservation, $roomIdsByType)
                : app(RoomHoldService::class)->createAdvanceHolds($reservation, $roomIds);

            $reservation->refresh();
            $reservation->issueGuestPaymentLink(rotateToken: true);
            $reservation->update([
                'payment_link_token' => $reservation->payment_link_token,
                'payment_link_expires_at' => $reservation->payment_link_expires_at,
            ]);

            $roomNumbers = Room::query()
                ->whereIn('id', $roomIds)
                ->pluck('room_number')
                ->filter()
                ->values()
                ->all();

            $logMessage = "Approved with {$result['room_count']} room(s) held: ".implode(', ', $roomNumbers).'.';

            ReservationLog::record(
                $reservation,
                'room_holds_created',
                $logMessage,
                ['room_ids' => $roomIds]
            );

            return [
                'reservation' => $reservation->fresh(),
                'room_count' => (int) $result['room_count'],
                'hold_error' => null,
            ];
        } catch (\RuntimeException $e) {
            return [
                'reservation' => $reservation->fresh(),
                'room_count' => 0,
                'hold_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, array<int, int>>
     */
    protected function normalizeAssignedRoomsByType(Reservation $reservation, array $data): array
    {
        $grouped = [];

        foreach ((array) ($data['assigned_room_ids_by_type'] ?? []) as $roomTypeId => $roomIds) {
            $roomIds = array_values(array_unique(array_filter(array_map('intval', (array) $roomIds))));
            if (! empty($roomIds)) {
                $grouped[(int) $roomTypeId] = $roomIds;
            }
        }

        if (! empty($grouped)) {
            return $grouped;
        }

        $roomIds = array_values(array_unique(array_filter(array_map('intval', (array) ($data['assigned_room_ids'] ?? [])))));
        if (empty($roomIds)) {
            return [];
        }

        return [(int) $reservation->preferred_room_type_id => $roomIds];
    }

    public function decline(Reservation $reservation, ?string $reason = null): Reservation
    {
        if ($reservation->status !== 'pending') {
            throw new \RuntimeException('Only pending reservations can be declined.');
        }

        app(RoomHoldService::class)->releaseAllHolds($reservation);

        $reservation->update([
            'status' => 'declined',
            'admin_notes' => $reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return $reservation->fresh();
    }

    public function cancel(Reservation $reservation, ?string $reason = null): Reservation
    {
        if (! in_array($reservation->status, ['pending', 'approved', 'confirmed'], true)) {
            throw new \RuntimeException('Only pending, approved, or confirmed reservations can be cancelled.');
        }

        app(RoomHoldService::class)->releaseAllHolds($reservation);

        $reservation->update([
            'status' => 'cancelled',
            'admin_notes' => $reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return $reservation->fresh();
    }

    public function autoCancelUnpaid(Reservation $reservation, int $hoursLimit): Reservation
    {
        $note = trim(
            ($reservation->admin_notes ?? '')
            ."\n\n[Auto-cancelled on ".now()->format('Y-m-d H:i')."] Payment not received within {$hoursLimit} hours of approval."
        );

        $reservation = $this->cancel($reservation, $note);

        ReservationLog::record(
            $reservation,
            'auto_cancelled',
            "Reservation auto-cancelled: Payment not received within {$hoursLimit} hours of approval.",
            [
                'approved_at' => $reservation->approved_at?->toIso8601String(),
                'hours_limit' => $hoursLimit,
            ]
        );

        return $reservation;
    }

    public function markConfirmedFromOnlinePayment(Reservation $reservation): Reservation
    {
        if ($reservation->status === 'approved' && ! $reservation->hasActiveAdvanceHold()) {
            ReservationLog::record(
                $reservation,
                'payment_received_without_room_hold',
                'Online payment was received, but reservation was not confirmed because no room hold exists.'
            );

            return $reservation->fresh();
        }

        $currentStatus = $reservation->status;
        $newStatus = match ($currentStatus) {
            'approved' => 'confirmed',
            default => $currentStatus,
        };

        if ($newStatus === $currentStatus) {
            return $reservation;
        }

        $updateData = ['status' => $newStatus];

        if ($currentStatus === 'pending' && empty($reservation->approved_at)) {
            $updateData['approved_at'] = now();
        }

        $reservation->update($updateData);

        return $reservation->fresh();
    }

    /**
     * Refresh the guest payment link for a reservation with protected inventory.
     *
     * @return array{reservation: Reservation, emailed: bool}
     */
    public function refreshGuestPaymentLink(Reservation $reservation, bool $emailGuest = false): array
    {
        if (! $reservation->canAcceptGuestPayment()) {
            throw new \RuntimeException('Only reservations with active room holds can receive refreshed payment links.');
        }

        $reservation->issueGuestPaymentLink(rotateToken: true);

        $reservation->update([
            'payment_link_token' => $reservation->payment_link_token,
            'payment_link_expires_at' => $reservation->payment_link_expires_at,
        ]);

        $freshReservation = $reservation->fresh();

        ReservationLog::record(
            $freshReservation,
            'payment_link_refreshed',
            'Guest payment link refreshed by '.(auth()->user()->name ?? 'system').'.',
            ['emailed' => $emailGuest && filled($freshReservation->guest_email)]
        );

        $emailed = false;

        if ($emailGuest && filled($freshReservation->guest_email)) {
            Mail::to($freshReservation->guest_email)->send(new SendPaymentLinkMail($freshReservation));
            $emailed = true;

            ReservationLog::record(
                $freshReservation,
                'payment_link_resent',
                'Refreshed payment link emailed to guest.',
                ['guest_email' => $freshReservation->guest_email]
            );
        }

        return [
            'reservation' => $freshReservation,
            'emailed' => $emailed,
        ];
    }

    public function checkOut(Reservation $reservation, mixed $checkedOutAt = null, ?string $remarks = null): Reservation
    {
        if (! in_array($reservation->status, ['checked_in', 'checked_out'], true)) {
            throw new \RuntimeException('Only checked-in or checked-out reservations can be checked out.');
        }

        $checkoutAt = $checkedOutAt ? Carbon::parse($checkedOutAt) : now();

        DB::transaction(function () use ($reservation, $checkoutAt, $remarks): void {
            RoomAssignment::where('reservation_id', $reservation->id)
                ->whereNull('checked_out_at')
                ->get()
                ->each(fn (RoomAssignment $assignment) => $assignment->update([
                    'status' => 'checked_out',
                    'checked_out_at' => $checkoutAt,
                    'checked_out_by' => auth()->id(),
                ]));

            if (filled($remarks)) {
                RoomAssignment::where('reservation_id', $reservation->id)
                    ->get()
                    ->each(function (RoomAssignment $assignment) use ($remarks): void {
                        $assignment->update([
                            'remarks' => $assignment->remarks
                                ? $assignment->remarks.' | '.$remarks
                                : $remarks,
                        ]);
                    });
            }

            $reservation->update(['status' => 'checked_out']);
        });

        return $reservation->fresh();
    }
}
