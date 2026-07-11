<?php

namespace App\Services;

use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\RoomResource;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomHold;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoomUtilizationCalendarService
{
    /**
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     floor_id?: int|string|null,
     *     room_type_id?: int|string|null,
     *     room_status?: string|null,
     *     visible_types?: array<int,string>|null
     * }  $filters
     * @return array<string,mixed>
     */
    public function build(array $filters = []): array
    {
        [$from, $to] = $this->resolveRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $dates = $this->buildDates($from, $to);
        $visibleTypes = collect($filters['visible_types'] ?? ['holds', 'assignments', 'room_states', 'unassigned'])
            ->filter()
            ->values()
            ->all();

        $rooms = $this->queryRooms($filters)->get();
        $roomIds = $rooms->pluck('id');
        $holds = $this->queryHolds($roomIds, $from, $to)->get();
        $assignments = $this->queryAssignments($roomIds, $from, $to)->get();
        $unassignedCount = $this->queryUnassignedReservations($from, $to)->count();

        $rows = $rooms->map(function (Room $room) use ($dates, $holds, $assignments, $visibleTypes): array {
            $roomHolds = $holds->where('room_id', $room->id);
            $roomAssignments = $assignments->where('room_id', $room->id);
            $cells = [];

            foreach ($dates as $date) {
                $dateKey = $date['key'];
                $cellEvents = [];

                if (in_array('room_states', $visibleTypes, true) && in_array($room->status, ['maintenance', 'inactive'], true)) {
                    $cellEvents[] = $this->roomStateEvent($room, $dateKey);
                }

                if (in_array('holds', $visibleTypes, true)) {
                    foreach ($roomHolds as $hold) {
                        if ($this->dateOverlaps($dateKey, $hold->hold_from, $hold->hold_to)) {
                            $cellEvents[] = $this->holdEvent($hold, $room);
                        }
                    }
                }

                if (in_array('assignments', $visibleTypes, true)) {
                    $cellEvents = array_merge(
                        $cellEvents,
                        $this->assignmentEventsForDate($roomAssignments, $room, $dateKey)
                    );
                }

                $cells[$dateKey] = [
                    'date' => $dateKey,
                    'events' => collect($cellEvents)->sortBy('priority')->values()->all(),
                    'slot_summary' => $this->slotSummary($room, $cellEvents),
                ];
            }

            return [
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'room_type' => $room->roomType?->name ?? 'Room type',
                'floor' => $room->floor?->name ?? 'No floor',
                'capacity' => max(1, (int) ($room->capacity ?? 1)),
                'status' => $room->status,
                'is_private' => (bool) ($room->roomType?->isPrivate() ?? false),
                'url' => RoomResource::getUrl('edit', ['record' => $room]),
                'cells' => $cells,
            ];
        })->values()->all();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'dates' => $dates,
            'rows' => $rows,
            'unassigned' => [],
            'summary' => $this->summary($rooms, $holds, $assignments, $unassignedCount, $from, $to),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(?string $dateFrom, ?string $dateTo): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::today()->startOfDay();
        $to = $dateTo ? Carbon::parse($dateTo)->startOfDay() : $from->copy()->addDays(14);

        if ($to->lessThan($from)) {
            $to = $from->copy();
        }

        if ($from->diffInDays($to) > 30) {
            $to = $from->copy()->addDays(30);
        }

        return [$from, $to];
    }

    /**
     * @return array<int,array{key:string,label:string,weekday:string,is_today:bool}>
     */
    protected function buildDates(Carbon $from, Carbon $to): array
    {
        $dates = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $dates[] = [
                'key' => $cursor->toDateString(),
                'label' => $cursor->format('M d'),
                'weekday' => $cursor->format('D'),
                'is_today' => $cursor->isToday(),
            ];
            $cursor->addDay();
        }

        return $dates;
    }

    protected function queryRooms(array $filters)
    {
        return Room::query()
            ->with(['roomType', 'floor'])
            ->when(filled($filters['floor_id'] ?? null), fn ($query) => $query->where('floor_id', $filters['floor_id']))
            ->when(filled($filters['room_type_id'] ?? null), fn ($query) => $query->where('room_type_id', $filters['room_type_id']))
            ->when(filled($filters['room_status'] ?? null), fn ($query) => $query->where('status', $filters['room_status']))
            ->orderBy('floor_id')
            ->orderBy('room_number');
    }

    protected function queryHolds(Collection $roomIds, Carbon $from, Carbon $to)
    {
        return RoomHold::query()
            ->advance()
            ->active()
            ->whereIn('room_id', $roomIds)
            ->conflictingWith($from, $to->copy()->addDay())
            ->with(['reservation', 'room.roomType']);
    }

    protected function queryAssignments(Collection $roomIds, Carbon $from, Carbon $to)
    {
        return RoomAssignment::query()
            ->whereIn('room_id', $roomIds)
            ->whereNotNull('checked_in_at')
            ->where(function ($query) use ($from, $to) {
                $query
                    ->whereDate('checked_in_at', '<=', $to->toDateString())
                    ->where(function ($inner) use ($from) {
                        $inner->whereNull('checked_out_at')
                            ->orWhereDate('checked_out_at', '>', $from->toDateString());
                    });
            })
            ->with(['reservation', 'room.roomType']);
    }

    public function queryUnassignedReservations(Carbon $from, Carbon $to)
    {
        return Reservation::query()
            ->with(['preferredRoomType', 'roomRequests.roomType'])
            ->whereIn('status', ['pending', 'approved', 'confirmed'])
            ->whereDoesntHave('roomHolds')
            ->whereDoesntHave('roomAssignments')
            ->where('check_in_date', '<=', $to->toDateString())
            ->where('check_out_date', '>', $from->toDateString())
            ->orderBy('check_in_date');
    }

    protected function dateOverlaps(string $dateKey, mixed $start, mixed $end): bool
    {
        $date = Carbon::parse($dateKey)->startOfDay();
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        return $date->greaterThanOrEqualTo($startDate) && $date->lessThan($endDate);
    }

    protected function holdEvent(RoomHold $hold, Room $room): array
    {
        $reservation = $hold->reservation;
        $status = $reservation?->status ?? 'approved';
        $slots = $room->roomType?->isPrivate()
            ? max(1, (int) ($room->capacity ?? 1))
            : max(1, (int) ($hold->held_guest_count ?? $reservation?->number_of_occupants ?? 1));

        return [
            'type' => 'hold',
            'status' => $status,
            'label' => ($reservation?->reference_number ?? 'Held').' - '.($reservation?->guest_name ?? 'Guest'),
            'slots' => $slots,
            'color' => $this->statusColor($status, 'hold'),
            'url' => $reservation ? ReservationResource::getUrl('view', ['record' => $reservation]) : null,
            'priority' => 20,
        ];
    }

    protected function assignmentEventsForDate(Collection $assignments, Room $room, string $dateKey): array
    {
        return $assignments
            ->filter(function (RoomAssignment $assignment) use ($dateKey): bool {
                [$assignmentStart, $assignmentEnd] = $this->assignmentRange($assignment);

                return $this->dateOverlaps($dateKey, $assignmentStart, $assignmentEnd);
            })
            ->groupBy(function (RoomAssignment $assignment): string {
                $checkedOut = filled($assignment->checked_out_at) || $assignment->status === 'checked_out';

                return ($assignment->reservation_id ?? 'none').($checkedOut ? '|checked_out' : '|checked_in');
            })
            ->map(fn (Collection $group): array => $this->assignmentEvent($group->first(), $room, $group->count()))
            ->values()
            ->all();
    }

    protected function assignmentEvent(RoomAssignment $assignment, Room $room, int $slotCount): array
    {
        $reservation = $assignment->reservation;
        $checkedOut = filled($assignment->checked_out_at) || $assignment->status === 'checked_out';
        $status = $checkedOut ? 'checked_out' : 'checked_in';
        $slots = $room->roomType?->isPrivate()
            ? max(1, (int) ($room->capacity ?? 1))
            : max(1, $slotCount);

        return [
            'type' => 'assignment',
            'status' => $status,
            'label' => ($reservation?->reference_number ?? 'Stay').' - '.($assignment->guest_first_name ?? $reservation?->guest_first_name ?? 'Guest'),
            'slots' => $slots,
            'color' => $this->statusColor($status, 'assignment'),
            'url' => $reservation ? ReservationResource::getUrl('view', ['record' => $reservation]) : null,
            'priority' => $checkedOut ? 40 : 10,
        ];
    }

    protected function roomStateEvent(Room $room, string $dateKey): array
    {
        return [
            'type' => 'room_state',
            'status' => $room->status,
            'label' => $room->status === 'maintenance' ? 'Out of Order' : 'Inactive',
            'slots' => max(1, (int) ($room->capacity ?? 1)),
            'color' => $room->status === 'maintenance' ? '#f59e0b' : '#6b7280',
            'url' => RoomResource::getUrl('edit', ['record' => $room]),
            'priority' => 0,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function assignmentRange(RoomAssignment $assignment): array
    {
        $start = Carbon::parse($assignment->checked_in_at)->startOfDay();
        $end = $assignment->checked_out_at
            ? Carbon::parse($assignment->checked_out_at)->startOfDay()
            : Carbon::parse(
                $assignment->detailed_checkout_datetime
                    ?? $assignment->reservation?->check_out_date
                    ?? Carbon::today()->addDay()
            )->startOfDay();

        if (! $assignment->checked_out_at && $end->lessThanOrEqualTo(Carbon::today())) {
            $end = Carbon::today()->addDay()->startOfDay();
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addDay();
        }

        return [$start, $end];
    }

    protected function slotSummary(Room $room, array $events): array
    {
        $capacity = max(1, (int) ($room->capacity ?? 1));
        $held = collect($events)->where('type', 'hold')->sum('slots');
        $occupied = collect($events)->where('status', 'checked_in')->sum('slots');

        return [
            'held' => min($capacity, (int) $held),
            'occupied' => min($capacity, (int) $occupied),
            'capacity' => $capacity,
            'label' => $occupied > 0
                ? min($capacity, (int) $occupied)."/{$capacity} occupied"
                : ($held > 0 ? min($capacity, (int) $held)."/{$capacity} held" : null),
        ];
    }

    protected function summary(Collection $rooms, Collection $holds, Collection $assignments, int $unassignedCount, Carbon $from, Carbon $to): array
    {
        $totalCapacity = $rooms->sum(fn (Room $room): int => max(1, (int) ($room->capacity ?? 1)));
        $heldSlots = $holds->sum(fn (RoomHold $hold): int => max(1, (int) ($hold->held_guest_count ?? $hold->reservation?->number_of_occupants ?? 1)));
        $occupiedSlots = $assignments->filter(fn (RoomAssignment $assignment): bool => blank($assignment->checked_out_at) && $assignment->status !== 'checked_out')->count();

        return [
            'rooms' => $rooms->count(),
            'capacity' => (int) $totalCapacity,
            'held_slots' => (int) $heldSlots,
            'occupied_slots' => (int) $occupiedSlots,
            'maintenance_rooms' => $rooms->where('status', 'maintenance')->count(),
            'inactive_rooms' => $rooms->where('status', 'inactive')->count(),
            'unassigned_reservations' => $unassignedCount,
            'period_label' => $from->format('M d, Y').' - '.$to->format('M d, Y'),
        ];
    }

    protected function statusColor(string $status, string $type): string
    {
        return match ($status) {
            'pending' => '#fbbf24',
            'approved' => '#919F02',
            'confirmed' => '#10B981',
            'checked_in' => '#16a34a',
            'checked_out' => '#94a3b8',
            default => $type === 'hold' ? '#919F02' : '#d1d5db',
        };
    }
}
