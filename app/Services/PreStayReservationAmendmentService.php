<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomHold;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Performs an all-or-nothing pre-stay amendment without rewriting payments or charges. */
class PreStayReservationAmendmentService
{
    public function amend(Reservation $reservation, array $data): Reservation
    {
        if (! in_array($reservation->status, ['approved', 'confirmed'], true)) {
            throw new \RuntimeException('Only approved or confirmed reservations can be amended before check-in.');
        }

        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date']);
        if ($checkOut->lte($checkIn)) {
            throw new \RuntimeException('The check-out date must be after the check-in date.');
        }

        return DB::transaction(function () use ($reservation, $data, $checkIn, $checkOut): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $old = $locked->only(['guest_name', 'guest_email', 'guest_phone', 'check_in_date', 'check_out_date', 'number_of_occupants']);
            $holds = $locked->roomHolds()->where('hold_type', 'advance')->lockForUpdate()->get();
            $previousRoomIds = $holds->pluck('room_id');
            $roomIds = array_values(array_unique(array_map('intval', $data['room_ids'] ?? $holds->pluck('room_id')->all())));
            if (count($roomIds) !== $holds->count() || empty($roomIds)) {
                throw new \RuntimeException('Select the same number of replacement rooms as the existing held rooms.');
            }

            $rooms = Room::query()->whereIn('id', $roomIds)->where('is_active', true)
                ->whereNotIn('status', ['maintenance', 'inactive'])->with('roomType')->lockForUpdate()->get();
            if ($rooms->count() !== count($roomIds)) {
                throw new \RuntimeException('One or more selected rooms are no longer active.');
            }

            // Remove this reservation's holds inside the transaction so it can revalidate its own rooms.
            $locked->roomHolds()->where('hold_type', 'advance')->delete();
            $occupants = max(1, (int) $data['number_of_occupants']);
            $remaining = $occupants;
            foreach ($rooms as $room) {
                $slots = $room->roomType?->isPrivate() ? null : min(max(1, (int) $room->capacity), $remaining);
                if ($slots !== null && $slots < 1 || app(RoomHoldService::class)->hasConflict($room, $checkIn, $checkOut, $slots)) {
                    throw new \RuntimeException("Room {$room->room_number} is unavailable for the amended stay.");
                }
                $remaining -= $slots ?? $remaining;
            }
            if ($remaining > 0) {
                throw new \RuntimeException('The selected rooms cannot accommodate the amended guest count.');
            }

            $updates = collect($data)->only(['guest_name', 'guest_email', 'guest_phone', 'check_in_date', 'check_out_date', 'number_of_occupants', 'purpose', 'special_requests'])->all();
            $locked->fill($updates)->save();
            $remaining = $occupants;
            foreach ($rooms as $room) {
                $slots = $room->roomType?->isPrivate() ? null : min(max(1, (int) $room->capacity), $remaining);
                RoomHold::create(['room_id' => $room->id, 'reservation_id' => $locked->id, 'hold_from' => $checkIn->toDateString(), 'hold_to' => $checkOut->toDateString(), 'hold_type' => 'advance', 'held_guest_count' => $slots, 'expires_at' => null]);
                $remaining -= $slots ?? $remaining;
            }
            app(RoomHoldService::class)->recalculateRoomStatuses($previousRoomIds->merge($rooms->pluck('id')));
            if (! $locked->payments()->where('status', 'posted')->exists()) {
                $locked->issueGuestPaymentLink(rotateToken: true);
                $locked->save();
            }
            ReservationLog::record($locked, 'reservation_amended', 'Pre-stay reservation amended.', ['before' => $old, 'after' => $locked->only(array_keys($old)), 'room_ids' => $roomIds]);

            return $locked->fresh();
        });
    }
}
