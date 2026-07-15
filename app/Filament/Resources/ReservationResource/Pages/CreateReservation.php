<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Pages\CreateRedirectToIndex as CreateRecord;
use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\ReservationWorkflowService;
use App\Services\RoomHoldService;
use App\Support\ReservationRoomRequests;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    /**
     * @var array<int, array{room_type_id:int,requested_capacity:int,requested_room_count:int,occupant_count:int,notes:?string,sort_order:int,room_ids:array<int,int>}>
     */
    protected array $directRoomAssignments = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->directRoomAssignments = $this->validatedDirectRoomAssignments(
            (array) ($data['direct_room_assignments'] ?? []),
            (string) ($data['check_in_date'] ?? ''),
            (string) ($data['check_out_date'] ?? ''),
        );

        unset($data['direct_room_assignments']);

        $data = ReservationRoomRequests::applyToReservationData($data, $this->directRoomAssignments);
        $data['status'] = 'pending';

        return $data;
    }

    /**
     * Create the reservation and room-request lines, then reuse the regular
     * approval workflow so exact rooms receive the same conflict-safe holds.
     * The parent CreateRecord transaction rolls everything back on failure.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Reservation $reservation */
        $reservation = new Reservation($data);
        $reservation->save();

        ReservationRoomRequests::persist($reservation, $this->directRoomAssignments);

        $requestLines = $reservation->roomRequests()
            ->orderBy('sort_order')
            ->get()
            ->values();

        $roomsByRequest = [];
        foreach ($requestLines as $index => $requestLine) {
            $roomsByRequest['request_'.$requestLine->id] = $this->directRoomAssignments[$index]['room_ids'] ?? [];
        }

        $result = app(ReservationWorkflowService::class)->approve($reservation, [
            'assigned_room_ids_by_type' => $roomsByRequest,
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        if ($result['hold_error']) {
            throw ValidationException::withMessages([
                'direct_room_assignments' => 'The reservation could not be confirmed: '.$result['hold_error'],
            ]);
        }

        return $result['reservation'];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Reservation confirmed')
            ->body('All selected rooms are held and the reservation is ready for payment follow-up.');
    }

    /**
     * @param  array<int, mixed>  $assignments
     * @return array<int, array{room_type_id:int,requested_capacity:int,requested_room_count:int,occupant_count:int,notes:?string,sort_order:int,room_ids:array<int,int>}>
     */
    protected function validatedDirectRoomAssignments(array $assignments, string $checkInDate, string $checkOutDate): array
    {
        Validator::make(
            ['direct_room_assignments' => $assignments],
            [
                'direct_room_assignments' => ['required', 'array', 'min:1', 'max:7'],
                'direct_room_assignments.*.room_type_id' => ['required', 'integer', 'exists:room_types,id'],
                'direct_room_assignments.*.requested_capacity' => ['required', 'integer', 'min:1'],
                'direct_room_assignments.*.occupant_count' => ['required', 'integer', 'min:1', 'max:200'],
                'direct_room_assignments.*.room_ids' => ['required', 'array', 'min:1'],
                'direct_room_assignments.*.room_ids.*' => ['required', 'integer', 'exists:rooms,id'],
                'direct_room_assignments.*.notes' => ['nullable', 'string', 'max:500'],
            ],
            [
                'direct_room_assignments.required' => 'Add at least one room assignment.',
                'direct_room_assignments.*.room_ids.required' => 'Select at least one available room for this line.',
            ],
        )->validate();

        $checkIn = Carbon::parse($checkInDate)->startOfDay();
        $checkOut = Carbon::parse($checkOutDate)->startOfDay();
        $errors = [];
        $normalized = [];
        $selectedRoomIds = [];
        $roomHoldService = app(RoomHoldService::class);

        foreach ($assignments as $index => $assignment) {
            $prefix = "direct_room_assignments.{$index}";
            $roomType = RoomType::query()
                ->where('is_active', true)
                ->find((int) ($assignment['room_type_id'] ?? 0));
            $capacity = max(1, (int) ($assignment['requested_capacity'] ?? 0));
            $rawRoomIds = array_values(array_map('intval', (array) ($assignment['room_ids'] ?? [])));
            $roomIds = array_values(array_unique($rawRoomIds));
            $occupants = max(1, (int) ($assignment['occupant_count'] ?? 0));

            if (! $roomType) {
                $errors["{$prefix}.room_type_id"] = 'Please select an active room type.';
                continue;
            }

            if (! in_array($capacity, $roomHoldService->getSellableCapacities($roomType)->all(), true)) {
                $errors["{$prefix}.requested_capacity"] = 'Select a valid capacity for this room type.';
                continue;
            }

            if (count($roomIds) !== count($rawRoomIds)) {
                $errors["{$prefix}.room_ids"] = 'A room can only be selected once in a reservation.';
                continue;
            }

            $duplicateRoomIds = array_intersect($selectedRoomIds, $roomIds);
            if (! empty($duplicateRoomIds)) {
                $errors["{$prefix}.room_ids"] = 'A room can only be selected once in a reservation.';
                continue;
            }

            $rooms = Room::query()
                ->whereIn('id', $roomIds)
                ->where('room_type_id', $roomType->id)
                ->where('capacity', $capacity)
                ->where('is_active', true)
                ->whereNotIn('status', ['maintenance', 'inactive'])
                ->get();

            if ($rooms->count() !== count($roomIds)) {
                $errors["{$prefix}.room_ids"] = 'One or more selected rooms do not match this room type or capacity.';
                continue;
            }

            $availableRoomIds = $roomHoldService
                ->getAvailableRooms($roomType, $checkIn, $checkOut, $capacity)
                ->pluck('id')
                ->all();

            if (! empty(array_diff($roomIds, $availableRoomIds))) {
                $errors["{$prefix}.room_ids"] = 'One or more selected rooms are no longer available for these dates.';
                continue;
            }

            if ($roomType->isPrivate() && $occupants > ($capacity * count($roomIds))) {
                $maximum = $capacity * count($roomIds);
                $errors["{$prefix}.occupant_count"] = "These rooms can accommodate up to {$maximum} guests.";
                continue;
            }

            $selectedRoomIds = [...$selectedRoomIds, ...$roomIds];
            $normalized[] = [
                'room_type_id' => $roomType->id,
                'requested_capacity' => $capacity,
                'requested_room_count' => count($roomIds),
                'occupant_count' => $occupants,
                'notes' => filled($assignment['notes'] ?? null) ? trim((string) $assignment['notes']) : null,
                'sort_order' => count($normalized),
                'room_ids' => $roomIds,
            ];
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
