<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomHold;
use App\Models\RoomType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RoomHoldService
{
    /**
     * Get the distinct sellable capacities for a room type.
     *
     * @return Collection<int, int>
     */
    public function getSellableCapacities(RoomType $roomType): Collection
    {
        return $this->rememberGuestAvailability(
            "capacities:{$roomType->id}",
            fn (): Collection => ($roomType->relationLoaded('rooms')
                ? $roomType->rooms->filter(fn (Room $room): bool => $room->is_active && ! in_array($room->status, ['maintenance', 'inactive'], true))
                : $this->getSellableRooms($roomType))
                ->pluck('capacity')
                ->map(fn ($capacity): int => max(1, (int) $capacity))
                ->unique()
                ->sort()
                ->values(),
        );
    }

    /**
     * Build a guest-facing availability summary for the current moment.
     *
     * @return array<string, mixed>
     */
    public function getCurrentAvailabilitySummary(RoomType $roomType, ?int $requestedGuests = null, ?int $capacity = null): array
    {
        return $this->rememberGuestAvailability(
            "current:{$roomType->id}:".($requestedGuests ?? 'all').':'.($capacity ?? 'all'),
            fn (): array => $this->getCurrentAvailabilitySummaryUncached($roomType, $requestedGuests, $capacity),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getCurrentAvailabilitySummaryUncached(RoomType $roomType, ?int $requestedGuests = null, ?int $capacity = null): array
    {
        $rooms = $this->getSellableRooms($roomType, $capacity);

        if ($roomType->isPrivate()) {
            $availableRoomsCount = $rooms->filter(fn (Room $room) => $room->isAvailable())->count();

            return [
                'available_rooms' => $rooms->filter(fn (Room $room) => $room->isAvailable())->values(),
                'available_rooms_count' => $availableRoomsCount,
                'available_beds_count' => null,
                'total_rooms_count' => $rooms->count(),
                'total_beds_count' => null,
                'availability_display_count' => $availableRoomsCount,
                'availability_display_unit' => 'rooms',
                'availability_label' => $this->formatAvailabilityLabel($availableRoomsCount, 'room'),
                'can_accommodate_requested_guests' => $this->canPrivateRoomTypeAccommodate($roomType, $availableRoomsCount, $requestedGuests, $capacity),
            ];
        }

        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();
        $roomAvailability = $rooms->map(function (Room $room) use ($today, $tomorrow) {
            $capacity = max(0, (int) ($room->capacity ?? 0));
            $reservedSlots = $this->getReservedSlotsForDates($room, $today, $tomorrow);

            return [
                'room' => $room,
                'available_slots' => max(0, $capacity - $reservedSlots),
                'capacity' => $capacity,
            ];
        });

        $availableRooms = $roomAvailability
            ->filter(fn (array $entry) => $entry['available_slots'] > 0)
            ->map(fn (array $entry) => $entry['room'])
            ->values();

        $totalBedsCount = $roomAvailability->sum('capacity');
        $availableBedsCount = $roomAvailability->sum('available_slots');
        $availableRoomsCount = $availableRooms->count();

        return [
            'available_rooms' => $availableRooms,
            'available_rooms_count' => $availableRoomsCount,
            'available_beds_count' => $availableBedsCount,
            'total_rooms_count' => $rooms->count(),
            'total_beds_count' => $totalBedsCount,
            'availability_display_count' => $availableBedsCount,
            'availability_display_unit' => 'beds',
            'availability_label' => $this->formatAvailabilityLabel($availableBedsCount, 'bed'),
            'can_accommodate_requested_guests' => $requestedGuests
                ? $availableBedsCount >= $requestedGuests
                : $availableBedsCount > 0,
        ];
    }

    private function rememberGuestAvailability(string $key, callable $callback): mixed
    {
        if (app()->environment('testing')) {
            return $callback();
        }

        return Cache::remember("guest-availability:{$key}", now()->addSeconds(30), $callback);
    }

    /**
     * Build a guest-facing availability summary for a specific date range.
     *
     * @return array<string, mixed>
     */
    public function getDateAvailabilitySummary(
        RoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $requestedGuests = null,
        ?int $capacity = null,
    ): array {
        $rooms = $this->getSellableRooms($roomType, $capacity);

        if ($roomType->isPrivate()) {
            $availableRooms = $this->getAvailableRooms($roomType, $checkIn, $checkOut, $capacity);
            $availableRoomsCount = $availableRooms->count();

            return [
                'available_rooms' => $availableRooms,
                'available_rooms_count' => $availableRoomsCount,
                'available_beds_count' => null,
                'total_rooms_count' => $rooms->count(),
                'total_beds_count' => null,
                'availability_display_count' => $availableRoomsCount,
                'availability_display_unit' => 'rooms',
                'availability_label' => $this->formatAvailabilityLabel($availableRoomsCount, 'room'),
                'can_accommodate_requested_guests' => $this->canPrivateRoomTypeAccommodate($roomType, $availableRoomsCount, $requestedGuests, $capacity),
            ];
        }

        $roomAvailability = $rooms->map(function (Room $room) use ($checkIn, $checkOut) {
            $reservedSlots = $this->getReservedSlotsForDates($room, $checkIn, $checkOut);
            $capacity = max(0, (int) ($room->capacity ?? 0));
            $availableSlots = max(0, $capacity - $reservedSlots);

            return [
                'room' => $room,
                'available_slots' => $availableSlots,
                'capacity' => $capacity,
            ];
        });

        $availableRooms = $roomAvailability
            ->filter(fn (array $entry) => $entry['available_slots'] > 0)
            ->map(fn (array $entry) => $entry['room'])
            ->values();

        $availableBedsCount = $roomAvailability->sum('available_slots');
        $totalBedsCount = $roomAvailability->sum('capacity');

        return [
            'available_rooms' => $availableRooms,
            'available_rooms_count' => $availableRooms->count(),
            'available_beds_count' => $availableBedsCount,
            'total_rooms_count' => $rooms->count(),
            'total_beds_count' => $totalBedsCount,
            'availability_display_count' => $availableBedsCount,
            'availability_display_unit' => 'beds',
            'availability_label' => $this->formatAvailabilityLabel($availableBedsCount, 'bed'),
            'can_accommodate_requested_guests' => $requestedGuests
                ? $availableBedsCount >= $requestedGuests
                : $availableBedsCount > 0,
        ];
    }

    /**
     * Check if a specific room is available for a given date range.
     * A room is available if no active hold overlaps the requested dates.
     */
    public function isRoomAvailable(Room $room, Carbon $checkIn, Carbon $checkOut, ?int $requestedSlots = null): bool
    {
        return ! $this->hasConflict($room, $checkIn, $checkOut, $requestedSlots);
    }

    /**
     * Check if a specific room has a conflicting hold for the given date range.
     * Checks both RoomHolds (advance reservations) and RoomAssignments (checked-in guests).
     */
    public function hasConflict(Room $room, Carbon $checkIn, Carbon $checkOut, ?int $requestedSlots = null): bool
    {
        $room->loadMissing('roomType');

        // Check for RoomHolds (advance reservations with assigned rooms)
        $conflictingHolds = RoomHold::query()
            ->where('room_id', $room->id)
            ->active()
            ->conflictingWith($checkIn, $checkOut)
            ->with('reservation:id,number_of_occupants')
            ->get();

        if ($room->roomType?->isPrivate()) {
            if ($conflictingHolds->isNotEmpty()) {
                return true;
            }
        } elseif ($conflictingHolds->isNotEmpty()) {
            $capacity = max(0, (int) ($room->capacity ?? 0));
            $reservedSlots = $conflictingHolds->sum(fn (RoomHold $hold): int => $this->resolveHoldGuestCount($hold));
            $neededSlots = max(1, (int) ($requestedSlots ?? 1));

            if ($capacity <= 0 || $reservedSlots + $neededSlots > $capacity) {
                return true;
            }
        }

        // Check for RoomAssignments (actual checked-in guests)
        // A room assignment conflicts if:
        // 1. The guest hasn't checked out yet (checked_out_at is null)
        // 2. The reservation's date range overlaps with the requested dates
        $assignmentQuery = \App\Models\RoomAssignment::query()
            ->where('room_id', $room->id)
            ->whereNull('checked_out_at') // Guest is still checked in
            ->whereHas('reservation', function ($query) use ($checkIn, $checkOut) {
                $query->where(function ($q) use ($checkIn, $checkOut) {
                    // Overlapping date ranges
                    $q->where('check_in_date', '<', $checkOut->format('Y-m-d'))
                      ->where('check_out_date', '>', $checkIn->format('Y-m-d'));
                });
            });

        if ($room->roomType?->isPrivate()) {
            return $assignmentQuery->exists();
        }

        $capacity = max(0, (int) ($room->capacity ?? 0));
        $checkedInSlots = $assignmentQuery->count();
        $reservedSlots = $this->getReservedHoldSlotsForDates($room, $checkIn, $checkOut);
        $neededSlots = max(1, (int) ($requestedSlots ?? 1));

        return $capacity <= 0 || $checkedInSlots + $reservedSlots + $neededSlots > $capacity;
    }

    /**
     * Get all rooms of a given room type that are available for a date range.
     *
     * @return Collection<int, Room>
     */
    public function getAvailableRooms(
        RoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $capacity = null,
        ?int $minimumAvailableSlots = null,
    ): Collection
    {
        $roomId = Room::query()
            ->where('room_type_id', $roomType->id)
            ->when($capacity !== null, fn ($query) => $query->where('capacity', $capacity))
            ->where('is_active', true)
            ->whereNotIn('status', ['maintenance', 'inactive'])
            ->pluck('id');

        if ($roomId->isEmpty()) {
            return collect();
        }

        return Room::query()
            ->whereIn('id', $roomId)
            ->with(['roomType', 'floor'])
            ->orderBy('room_number')
            ->get()
            ->filter(function (Room $room) use ($roomType, $checkIn, $checkOut, $minimumAvailableSlots): bool {
                if ($roomType->isPrivate()) {
                    return ! $this->hasConflict($room, $checkIn, $checkOut);
                }

                return $this->availableSlotsForDates($room, $checkIn, $checkOut) >= max(1, (int) ($minimumAvailableSlots ?? 1));
            })
            ->values();
    }

    /** Return the sellable dorm beds in a room for a proposed stay. */
    public function availableSlotsForDates(Room $room, Carbon $checkIn, Carbon $checkOut): int
    {
        $room->loadMissing('roomType');

        if ($room->roomType?->isPrivate()) {
            return $this->hasConflict($room, $checkIn, $checkOut)
                ? 0
                : max(1, (int) $room->capacity);
        }

        return max(0, (int) $room->capacity - $this->getReservedSlotsForDates($room, $checkIn, $checkOut));
    }

    /**
     * Validate a staff-selected set of dorm rooms against their combined
     * remaining beds for the reservation's exact date range.
     */
    public function selectedDormRoomsCanAccommodate(Collection $rooms, Carbon $checkIn, Carbon $checkOut, int $guestCount): bool
    {
        return $rooms->sum(fn (Room $room): int => $this->availableSlotsForDates($room, $checkIn, $checkOut)) >= max(1, $guestCount);
    }

    /**
     * Get the count of available rooms for a room type and date range.
     */
    public function getAvailableRoomCount(RoomType $roomType, Carbon $checkIn, Carbon $checkOut, ?int $capacity = null): int
    {
        return $this->getAvailableRooms($roomType, $checkIn, $checkOut, $capacity)->count();
    }

    /**
     * Determine whether a room type can fulfil a room-request line. For shared
     * rooms, this considers the free beds in the selected number of rooms,
     * rather than merely the total number of free beds across the whole type.
     */
    public function canAccommodateRoomRequest(
        RoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
        int $guestCount,
        int $roomCount,
    ): bool {
        $rooms = $this->getAvailableRooms($roomType, $checkIn, $checkOut);
        $roomCount = max(1, $roomCount);

        if ($rooms->count() < $roomCount) {
            return false;
        }

        if ($roomType->isPrivate()) {
            return $rooms->sortByDesc(fn (Room $room): int => max(0, (int) $room->capacity))
                ->take($roomCount)
                ->sum(fn (Room $room): int => max(0, (int) $room->capacity)) >= max(1, $guestCount);
        }

        return $rooms->map(fn (Room $room): int => max(0, (int) $room->capacity - $this->getReservedSlotsForDates($room, $checkIn, $checkOut)))
            ->sortDesc()
            ->take($roomCount)
            ->sum() >= max(1, $guestCount);
    }

    /**
     * Get remaining guest capacity for a room type and date range.
     */
    public function getAvailableCapacity(RoomType $roomType, Carbon $checkIn, Carbon $checkOut): int
    {
        return (int) ($this->getDateAvailabilitySummary($roomType, $checkIn, $checkOut)['available_beds_count'] ?? 0);
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

        DB::transaction(function () use ($reservation, $rooms, $checkIn, $checkOut, $isPrivate, &$holds) {
            $guestAllocation = $this->allocateGuestsAcrossRooms($reservation, $rooms);

            foreach ($rooms as $room) {
                $hold = RoomHold::create([
                    'room_id' => $room->id,
                    'reservation_id' => $reservation->id,
                    'hold_from' => $checkIn->toDateString(),
                    'hold_to' => $checkOut->toDateString(),
                    'hold_type' => 'advance',
                    'held_guest_count' => $isPrivate ? null : ($guestAllocation[$room->id] ?? 1),
                    'expires_at' => null, // No expiry for advance holds
                ]);

                $holds[] = $hold;
            }

            // Auto-transition from 'approved' to 'confirmed' when rooms are assigned
            if ($reservation->status === 'approved') {
                $reservation->update(['status' => 'confirmed']);
            }
        });

        $this->recalculateRoomStatuses($rooms->pluck('id'));

        return [
            'holds' => $holds,
            'room_count' => count($holds),
        ];
    }

    /**
     * Create advance holds from room IDs grouped by reservation room request.
     *
     * @param  array<int|string, array<int, int>>  $roomIdsByRoomType
     * @return array<string, mixed>
     */
    public function createAdvanceHoldsByRoomType(Reservation $reservation, array $roomIdsByRoomType): array
    {
        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);
        $requests = $reservation->getEffectiveRoomRequests();
        $requestsById = $requests->filter(fn ($request) => filled($request->id))->keyBy('id');
        $requestsByType = $requests->groupBy('room_type_id');
        $holds = [];

        DB::transaction(function () use ($reservation, $roomIdsByRoomType, $requestsById, $requestsByType, $checkIn, $checkOut, &$holds): void {
            foreach ($roomIdsByRoomType as $requestKey => $roomIds) {
                $roomIds = array_values(array_unique(array_filter(array_map('intval', (array) $roomIds))));

                if (empty($roomIds)) {
                    continue;
                }

                $requestLine = str_starts_with((string) $requestKey, 'request_')
                    ? $requestsById->get((int) substr((string) $requestKey, 8))
                    : $requestsByType->get((int) $requestKey)?->first();
                if (! $requestLine) {
                    throw new \RuntimeException('One or more selected rooms do not match the guest requested room types.');
                }

                $rooms = Room::query()
                    ->whereIn('id', $roomIds)
                    ->where('room_type_id', $requestLine->room_type_id)
                    ->when($requestLine->requested_capacity, fn ($query, $capacity) => $query->where('capacity', $capacity))
                    ->where('is_active', true)
                    ->whereNotIn('status', ['maintenance', 'inactive'])
                    ->with('roomType')
                    ->get()
                    ->sortBy(fn (Room $room) => array_search($room->id, $roomIds, true))
                    ->values();

                if ($rooms->count() !== count($roomIds)) {
                    throw new \RuntimeException('One or more selected rooms are not valid for this room type.');
                }

                $isPrivate = $rooms->first()?->roomType?->isPrivate() ?? true;
                $guestAllocation = $isPrivate
                    ? $rooms->mapWithKeys(fn (Room $room) => [$room->id => null])->all()
                    : $this->allocateGuestsAcrossRooms($reservation, $rooms, (int) $requestLine->occupant_count);

                foreach ($rooms as $room) {
                    $slotsNeeded = $isPrivate ? null : max(1, (int) ($guestAllocation[$room->id] ?? 1));
                    if ($this->hasConflict($room, $checkIn, $checkOut, $slotsNeeded)) {
                        throw new \RuntimeException("Room {$room->room_number} is not yet available for the selected dates.");
                    }
                }

                foreach ($rooms as $room) {
                    $holds[] = RoomHold::create([
                        'room_id' => $room->id,
                        'reservation_id' => $reservation->id,
                        'hold_from' => $checkIn->toDateString(),
                        'hold_to' => $checkOut->toDateString(),
                        'hold_type' => 'advance',
                        'held_guest_count' => $isPrivate ? null : ($guestAllocation[$room->id] ?? 1),
                        'expires_at' => null,
                    ]);
                }
            }

            if (! empty($holds) && $reservation->status === 'approved') {
                $reservation->update(['status' => 'confirmed']);
            }
        });

        if (empty($holds)) {
            throw new \RuntimeException('At least one room must be selected.');
        }

        $this->recalculateRoomStatuses(collect($holds)->pluck('room_id'));

        return [
            'holds' => $holds,
            'room_count' => count($holds),
        ];
    }

    /**
     * Release all holds for a reservation (e.g., on cancellation or decline).
     */
    public function releaseAllHolds(Reservation $reservation): int
    {
        $roomIds = $reservation->roomHolds()->pluck('room_id')->unique();
        $count = $reservation->roomHolds()->delete();

        $this->recalculateRoomStatuses($roomIds);

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
        $guestQueue = collect($guestData)->values();
        $remainingHolds = $holds->count();

        foreach ($holds as $hold) {
            $room = $hold->room;
            $isPrivate = $room->roomType?->isPrivate() ?? false;
            $remainingHolds = max(1, $remainingHolds);
            $guestCount = $hold->held_guest_count
                ? max(1, (int) $hold->held_guest_count)
                : max(1, (int) ceil(max(1, $guestQueue->count()) / $remainingHolds));
            $roomGuests = $guestQueue->splice(0, $guestCount)->values()->all();

            $roomEntries[] = [
                'room_id' => $room->id,
                'room_mode' => $isPrivate ? 'private' : 'dorm',
                'guests' => ! empty($roomGuests) ? $roomGuests : $guestData,
            ];

            $remainingHolds--;
        }

        return $roomEntries;
    }

    /**
     * Delete holds for a reservation after check-in (they're no longer needed).
     */
    public function clearHoldsAfterCheckIn(Reservation $reservation): int
    {
        $roomIds = $reservation->roomHolds()->pluck('room_id')->unique();
        $count = $reservation->roomHolds()->count();
        $reservation->roomHolds()->delete();
        $this->recalculateRoomStatuses($roomIds);

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

    /** Recalculate persisted operational statuses for rooms affected by a hold change. */
    public function recalculateRoomStatuses(iterable $roomIds): void
    {
        $ids = collect($roomIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        Room::query()->whereIn('id', $ids)->with('roomType')->get()
            ->each(fn (Room $room) => $room->recalculateStatus());
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

        return [
            'advance_holds' => $activeAdvanceHolds,
            'advance_count' => $activeAdvanceHolds->count(),
        ];
    }

    /**
     * Get guest-bookable rooms for a room type.
     *
     * @return Collection<int, Room>
     */
    protected function getSellableRooms(RoomType $roomType, ?int $capacity = null): Collection
    {
        return Room::query()
            ->where('room_type_id', $roomType->id)
            ->when($capacity !== null, fn ($query) => $query->where('capacity', $capacity))
            ->where('is_active', true)
            ->whereNotIn('status', ['maintenance', 'inactive'])
            ->with([
                'roomType',
                'floor',
                'roomAssignments' => fn ($query) => $query
                    ->select(['id', 'room_id', 'status'])
                    ->where('status', 'checked_in'),
            ])
            ->orderBy('room_number')
            ->get();
    }

    protected function canPrivateRoomTypeAccommodate(RoomType $roomType, int $availableRoomsCount, ?int $requestedGuests = null, ?int $capacity = null): bool
    {
        if ($availableRoomsCount <= 0) {
            return false;
        }

        if ($requestedGuests === null) {
            return true;
        }

        return $requestedGuests <= ($capacity ?? $roomType->capacity ?? 0);
    }

    protected function formatAvailabilityLabel(int $count, string $singularUnit): string
    {
        $unit = $count === 1 ? $singularUnit : "{$singularUnit}s";

        return "{$count} {$unit} available";
    }

    protected function getReservedSlotsForDates(Room $room, Carbon $checkIn, Carbon $checkOut): int
    {
        $heldSlots = RoomHold::query()
            ->where('room_id', $room->id)
            ->active()
            ->conflictingWith($checkIn, $checkOut)
            ->with('reservation:id,number_of_occupants')
            ->get()
            ->sum(fn (RoomHold $hold): int => $this->resolveHoldGuestCount($hold));

        $checkedInSlots = RoomAssignment::query()
            ->where('room_id', $room->id)
            ->whereNull('checked_out_at')
            ->whereHas('reservation', function ($query) use ($checkIn, $checkOut) {
                $query->where('check_in_date', '<', $checkOut->format('Y-m-d'))
                    ->where('check_out_date', '>', $checkIn->format('Y-m-d'));
            })
            ->count();

        return min(max(0, (int) ($room->capacity ?? 0)), $heldSlots + $checkedInSlots);
    }

    protected function getReservedHoldSlotsForDates(Room $room, Carbon $checkIn, Carbon $checkOut): int
    {
        return RoomHold::query()
            ->where('room_id', $room->id)
            ->active()
            ->conflictingWith($checkIn, $checkOut)
            ->with('reservation:id,number_of_occupants')
            ->get()
            ->sum(fn (RoomHold $hold): int => $this->resolveHoldGuestCount($hold));
    }

    protected function resolveHoldGuestCount(RoomHold $hold): int
    {
        return max(1, (int) ($hold->held_guest_count ?? $hold->reservation?->number_of_occupants ?? 1));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Room>  $rooms
     * @return array<int, int>
     */
    protected function allocateGuestsAcrossRooms(Reservation $reservation, Collection $rooms, ?int $guestCount = null): array
    {
        $remainingGuests = max(1, (int) ($guestCount ?? $reservation->number_of_occupants ?? 1));
        $allocation = [];

        foreach ($rooms as $room) {
            $capacity = max(1, (int) ($room->capacity ?? 1));
            $slots = min($remainingGuests, $capacity);
            $allocation[$room->id] = max(1, $slots);
            $remainingGuests -= $slots;

            if ($remainingGuests <= 0) {
                break;
            }
        }

        foreach ($rooms as $room) {
            $allocation[$room->id] ??= 1;
        }

        return $allocation;
    }
}
