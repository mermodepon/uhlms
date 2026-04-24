<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomHold;
use App\Models\RoomType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomHoldService
{
    /**
     * Check if a specific room is available for a given date range.
     * A room is available if no active hold overlaps the requested dates.
     */
    public function isRoomAvailable(Room $room, Carbon $checkIn, Carbon $checkOut): bool
    {
        return ! $this->hasConflict($room, $checkIn, $checkOut);
    }

    /**
     * Check if a specific room has a conflicting hold for the given date range.
     * Checks both RoomHolds (advance reservations) and RoomAssignments (checked-in guests).
     */
    public function hasConflict(Room $room, Carbon $checkIn, Carbon $checkOut): bool
    {
        // Check for RoomHolds (advance reservations with assigned rooms)
        $hasHoldConflict = RoomHold::query()
            ->where('room_id', $room->id)
            ->active()
            ->conflictingWith($checkIn, $checkOut)
            ->exists();

        if ($hasHoldConflict) {
            return true;
        }

        // Check for RoomAssignments (actual checked-in guests)
        // A room assignment conflicts if:
        // 1. The guest hasn't checked out yet (checked_out_at is null)
        // 2. The reservation's date range overlaps with the requested dates
        $hasAssignmentConflict = \App\Models\RoomAssignment::query()
            ->where('room_id', $room->id)
            ->whereNull('checked_out_at') // Guest is still checked in
            ->whereHas('reservation', function ($query) use ($checkIn, $checkOut) {
                $query->where(function ($q) use ($checkIn, $checkOut) {
                    // Overlapping date ranges
                    $q->where('check_in_date', '<', $checkOut->format('Y-m-d'))
                      ->where('check_out_date', '>', $checkIn->format('Y-m-d'));
                });
            })
            ->exists();

        return $hasAssignmentConflict;
    }

    /**
     * Get all rooms of a given room type that are available for a date range.
     *
     * @return Collection<int, Room>
     */
    public function getAvailableRooms(RoomType $roomType, Carbon $checkIn, Carbon $checkOut): Collection
    {
        $roomId = Room::query()
            ->where('room_type_id', $roomType->id)
            ->where('is_active', true)
            ->whereNotIn('status', ['maintenance', 'inactive'])
            ->pluck('id');

        if ($roomId->isEmpty()) {
            return collect();
        }

        // Find rooms that have NO conflicting active holds or assignments
        $conflictingRoomIds = RoomHold::query()
            ->whereIn('room_id', $roomId)
            ->active()
            ->conflictingWith($checkIn, $checkOut)
            ->pluck('room_id')
            ->unique();

        // Also check for rooms with active assignments (checked-in guests)
        $assignmentConflictIds = \App\Models\RoomAssignment::query()
            ->whereIn('room_id', $roomId)
            ->whereNull('checked_out_at') // Guest is still checked in
            ->whereHas('reservation', function ($query) use ($checkIn, $checkOut) {
                $query->where(function ($q) use ($checkIn, $checkOut) {
                    // Overlapping date ranges
                    $q->where('check_in_date', '<', $checkOut->format('Y-m-d'))
                      ->where('check_out_date', '>', $checkIn->format('Y-m-d'));
                });
            })
            ->pluck('room_id')
            ->unique();

        // Merge both conflict lists
        $allConflictingIds = $conflictingRoomIds->merge($assignmentConflictIds)->unique();

        return Room::query()
            ->whereIn('id', $roomId)
            ->whereNotIn('id', $allConflictingIds)
            ->with(['roomType', 'floor'])
            ->orderBy('room_number')
            ->get();
    }

    /**
     * Get the count of available rooms for a room type and date range.
     */
    public function getAvailableRoomCount(RoomType $roomType, Carbon $checkIn, Carbon $checkOut): int
    {
        return $this->getAvailableRooms($roomType, $checkIn, $checkOut)->count();
    }

    /**
     * Create an advance hold on specific rooms for a reservation's date range.
     * This is called when staff approves a reservation with room assignment.
     *
     * @param  array<int, int>  $roomIds  Array of room IDs to hold
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function createAdvanceHolds(Reservation $reservation, array $roomIds): array
    {
        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);
        $roomType = $reservation->preferredRoomType;
        $isPrivate = $roomType?->isPrivate() ?? false;

        if (empty($roomIds)) {
            throw new \RuntimeException('At least one room must be selected.');
        }

        // Validate all rooms belong to the correct room type and are available
        $rooms = Room::query()
            ->whereIn('id', $roomIds)
            ->where('room_type_id', $reservation->preferred_room_type_id)
            ->where('is_active', true)
            ->whereNotIn('status', ['maintenance', 'inactive'])
            ->with('roomType')
            ->get();

        if ($rooms->count() !== count($roomIds)) {
            throw new \RuntimeException('One or more selected rooms are not valid for this room type.');
        }

        // Check each room for conflicts
        foreach ($rooms as $room) {
            if ($this->hasConflict($room, $checkIn, $checkOut)) {
                throw new \RuntimeException("Room {$room->room_number} is not yet available for the selected dates.");
            }
        }

        $holds = [];

        DB::transaction(function () use ($reservation, $rooms, $checkIn, $checkOut, &$holds) {
            foreach ($rooms as $room) {
                $hold = RoomHold::create([
                    'room_id' => $room->id,
                    'reservation_id' => $reservation->id,
                    'hold_from' => $checkIn->toDateString(),
                    'hold_to' => $checkOut->toDateString(),
                    'hold_type' => 'advance',
                    'expires_at' => null, // No expiry for advance holds
                ]);

                $holds[] = $hold;
            }

            // Auto-transition from 'approved' to 'confirmed' when rooms are assigned
            if ($reservation->status === 'approved') {
                $reservation->update(['status' => 'confirmed']);
            }
        });

        return [
            'holds' => $holds,
            'room_count' => count($holds),
        ];
    }

    /**
     * Create a short-term hold (for preparePendingPayment flow).
     * This integrates with the existing CheckInService::preparePendingPayment.
     *
     * @param  array<int, array{room_id: int}>  $roomEntries
     */
    public function createShortTermHolds(
        Reservation $reservation,
        array $roomEntries,
        Carbon $checkIn,
        Carbon $checkOut,
        Carbon $expiresAt
    ): void {
        DB::transaction(function () use ($reservation, $roomEntries, $checkIn, $checkOut, $expiresAt) {
            foreach ($roomEntries as $entry) {
                $roomId = $entry['room_id'] ?? null;
                if (! $roomId) {
                    continue;
                }

                RoomHold::create([
                    'room_id' => $roomId,
                    'reservation_id' => $reservation->id,
                    'hold_from' => $checkIn->toDateString(),
                    'hold_to' => $checkOut->toDateString(),
                    'hold_type' => 'short_term',
                    'expires_at' => $expiresAt,
                ]);
            }
        });
    }

    /**
     * Release all holds for a reservation (e.g., on cancellation or decline).
     */
    public function releaseAllHolds(Reservation $reservation): int
    {
        $count = $reservation->roomHolds()->delete();

        if ($count > 0) {
            ReservationLog::record(
                $reservation,
                'room_holds_released',
                "All room holds released for reservation #{$reservation->reference_number}.",
                ['released_count' => $count]
            );
        }

        return $count;
    }

    /**
     * Release expired short-term holds.
     * Also updates the rooms.status back to available via recalculateStatus.
     */
    public function releaseExpiredHolds(): int
    {
        $expiredHolds = RoomHold::query()
            ->shortTerm()
            ->expired()
            ->with('room')
            ->get();

        $affectedReservationIds = $expiredHolds->pluck('reservation_id')->unique();

        if ($expiredHolds->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($expiredHolds) {
            foreach ($expiredHolds as $hold) {
                $room = $hold->room;
                if ($room && $room->status === 'reserved') {
                    $room->recalculateStatus();
                }
                $hold->delete();
            }
        });

        // Update affected reservations back to approved
        foreach ($affectedReservationIds as $resId) {
            $reservation = Reservation::find($resId);
            if ($reservation && $reservation->status === 'pending_payment') {
                // Check if all holds are gone
                if ($reservation->roomHolds()->count() === 0) {
                    $reservation->update([
                        'status' => 'approved',
                        'checkin_hold_payload' => null,
                        'checkin_hold_started_at' => null,
                        'checkin_hold_expires_at' => null,
                        'checkin_hold_by' => null,
                    ]);

                    ReservationLog::record(
                        $reservation,
                        'room_holds_expired',
                        "Room holds expired for reservation #{$reservation->reference_number}. Status reverted to approved."
                    );
                }
            }
        }

        return $expiredHolds->count();
    }

    /**
     * Convert advance holds to RoomAssignments during check-in.
     * Returns the room entries formatted for CheckInService::execute.
     *
     * @return array<int, array{room_id: int, room_mode: string, guests: array}>
     */
    public function convertHoldsToRoomEntries(Reservation $reservation, array $guestData): array
    {
        $holds = $reservation->roomHolds()->advance()->with('room.roomType')->get();

        if ($holds->isEmpty()) {
            return [];
        }

        $roomEntries = [];

        foreach ($holds as $hold) {
            $room = $hold->room;
            $isPrivate = $room->roomType?->isPrivate() ?? false;

            $roomEntries[] = [
                'room_id' => $room->id,
                'room_mode' => $isPrivate ? 'private' : 'dorm',
                'guests' => $guestData,
            ];
        }

        return $roomEntries;
    }

    /**
     * Delete holds for a reservation after check-in (they're no longer needed).
     */
    public function clearHoldsAfterCheckIn(Reservation $reservation): int
    {
        $count = $reservation->roomHolds()->count();
        $reservation->roomHolds()->delete();

        return $count;
    }

    /**
     * Release advance holds for a reservation (e.g., when staff wants to change rooms).
     */
    public function releaseAdvanceHolds(Reservation $reservation): int
    {
        // Get room IDs before deletion so we can recalculate
        $roomIds = $reservation->roomHolds()->advance()->pluck('room_id')->unique();

        $count = $reservation->roomHolds()->advance()->count();
        $reservation->roomHolds()->advance()->delete();

        if ($count > 0) {
            // Recalculate status for released rooms
            foreach ($roomIds as $roomId) {
                $room = Room::find($roomId);
                if ($room) {
                    $room->recalculateStatus();
                }
            }

            ReservationLog::record(
                $reservation,
                'room_holds_released',
                "Advance room holds released for reservation #{$reservation->reference_number}.",
                ['released_count' => $count]
            );
        }

        return $count;
    }

    /**
     * Get holds summary for reporting.
     */
    public function getHoldsSummary(?Carbon $date = null): array
    {
        $date = $date ?? now();

        $activeAdvanceHolds = RoomHold::query()
            ->advance()
            ->where('hold_from', '<=', $date->toDateString())
            ->where('hold_to', '>', $date->toDateString())
            ->with(['reservation', 'room'])
            ->get();

        $activeShortTermHolds = RoomHold::query()
            ->shortTerm()
            ->active()
            ->with(['reservation', 'room'])
            ->get();

        return [
            'advance_holds' => $activeAdvanceHolds,
            'short_term_holds' => $activeShortTermHolds,
            'advance_count' => $activeAdvanceHolds->count(),
            'short_term_count' => $activeShortTermHolds->count(),
        ];
    }
}
