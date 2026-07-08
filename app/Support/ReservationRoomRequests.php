<?php

namespace App\Support;

use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\RoomHoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReservationRoomRequests
{
    /**
     * @return array<int, array{room_type_id:int,requested_room_count:int,occupant_count:int,notes:?string,sort_order:int}>
     */
    public static function fromRequest(Request $request): array
    {
        $lines = [];

        if ($request->filled('preferred_room_type_id')) {
            $lines[] = [
                'room_type_id' => (int) $request->input('preferred_room_type_id'),
                'requested_room_count' => max(1, (int) $request->input('requested_room_count', 1)),
                'occupant_count' => max(1, (int) $request->input('number_of_occupants', 1)),
                'notes' => null,
                'sort_order' => 0,
            ];
        }

        foreach ((array) $request->input('room_requests', []) as $line) {
            if (! is_array($line) || empty($line['room_type_id'])) {
                continue;
            }

            $lines[] = [
                'room_type_id' => (int) $line['room_type_id'],
                'requested_room_count' => max(1, (int) ($line['requested_room_count'] ?? 1)),
                'occupant_count' => max(1, (int) ($line['occupant_count'] ?? 1)),
                'notes' => filled($line['notes'] ?? null) ? trim((string) $line['notes']) : null,
                'sort_order' => count($lines),
            ];
        }

        return collect($lines)
            ->groupBy('room_type_id')
            ->map(function ($group) {
                $first = $group->first();
                $notes = $group
                    ->pluck('notes')
                    ->filter()
                    ->implode("\n");

                return [
                    'room_type_id' => (int) $first['room_type_id'],
                    'requested_room_count' => $group->sum(fn (array $line): int => max(1, (int) $line['requested_room_count'])),
                    'occupant_count' => $group->sum(fn (array $line): int => max(1, (int) $line['occupant_count'])),
                    'notes' => $notes !== '' ? $notes : null,
                    'sort_order' => (int) $first['sort_order'],
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->map(function (array $line, int $index): array {
                $line['sort_order'] = $index;

                return $line;
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{errors: array<string, string>, warnings: array<int, string>, summary: string}
     */
    public static function validateAvailability(array $lines, string $checkInDate, string $checkOutDate): array
    {
        $errors = [];
        $warnings = [];
        $summaries = [];
        $roomTypes = RoomType::query()
            ->whereIn('id', collect($lines)->pluck('room_type_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        foreach ($lines as $index => $line) {
            $extraIndex = max(0, $index - 1);
            $prefix = $index === 0 ? 'number_of_occupants' : "room_requests.{$extraIndex}.occupant_count";
            $roomType = $roomTypes->get((int) ($line['room_type_id'] ?? 0));

            if (! $roomType || ! $roomType->is_active) {
                $errors[$index === 0 ? 'preferred_room_type_id' : "room_requests.{$extraIndex}.room_type_id"] = 'Please select an active room type.';
                continue;
            }

            $requestedRooms = max(1, (int) ($line['requested_room_count'] ?? 1));
            $occupants = max(1, (int) ($line['occupant_count'] ?? 1));
            $summary = app(RoomHoldService::class)->getDateAvailabilitySummary(
                $roomType,
                Carbon::parse($checkInDate),
                Carbon::parse($checkOutDate),
                $occupants
            );

            $summaries[] = self::formatLineSummary($roomType, $requestedRooms, $occupants);

            if ($roomType->isPrivate()) {
                $capacity = max(1, (int) ($roomType->capacity ?? 1));
                $maxGuests = $requestedRooms * $capacity;

                if ($occupants > $maxGuests) {
                    $errors[$prefix] = "This request allows up to {$maxGuests} occupants across {$requestedRooms} room(s).";
                    continue;
                }

                if ((int) ($summary['available_rooms_count'] ?? 0) < $requestedRooms) {
                    $warnings[] = "{$roomType->name} currently shows {$summary['availability_label']} for the selected dates.";
                }

                continue;
            }

            $availableBeds = max(0, (int) ($summary['available_beds_count'] ?? 0));
            if ($occupants > $availableBeds) {
                $errors[$prefix] = $availableBeds > 0
                    ? "Only {$availableBeds} beds are available for {$roomType->name} on these dates."
                    : "No beds are available for {$roomType->name} on these dates.";
                continue;
            }

            if ((int) ($summary['available_rooms_count'] ?? 0) < $requestedRooms) {
                $warnings[] = "{$roomType->name} currently has fewer rooms with open beds than requested.";
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => implode('; ', $summaries),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public static function applyToReservationData(array $data, array $lines): array
    {
        $firstLine = $lines[0] ?? null;

        if ($firstLine) {
            $data['preferred_room_type_id'] = (int) $firstLine['room_type_id'];
            $data['number_of_occupants'] = collect($lines)->sum(fn (array $line): int => max(1, (int) $line['occupant_count']));
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public static function persist(Reservation $reservation, array $lines): void
    {
        $reservation->roomRequests()->delete();

        $reservation->roomRequests()->createMany(
            collect($lines)
                ->map(fn (array $line, int $index): array => [
                    'room_type_id' => (int) $line['room_type_id'],
                    'requested_room_count' => max(1, (int) ($line['requested_room_count'] ?? 1)),
                    'occupant_count' => max(1, (int) ($line['occupant_count'] ?? 1)),
                    'sort_order' => $index,
                    'notes' => filled($line['notes'] ?? null) ? (string) $line['notes'] : null,
                ])
                ->all()
        );
    }

    protected static function formatLineSummary(RoomType $roomType, int $requestedRooms, int $occupants): string
    {
        $roomWord = $requestedRooms === 1 ? 'room' : 'rooms';
        $guestWord = $occupants === 1 ? 'guest' : 'guests';

        return "{$requestedRooms} {$roomType->name} {$roomWord}, {$occupants} {$guestWord}";
    }
}
