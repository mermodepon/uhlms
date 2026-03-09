<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    /**
     * Execute direct-entry check-in for one reservation across multiple room entries.
     *
     * @param  Reservation  $reservation
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function execute(Reservation $reservation, array $payload, array $options = []): array
    {
        $entries = $payload['reservation_rooms'] ?? [];
        $useHeldLocks = (bool) ($options['use_held_locks'] ?? false);

        $checkedInCount = 0;
        $maleCount = 0;
        $femaleCount = 0;
        $failedGuests = [];
        $roomErrors = [];
        $allSucceeded = true;
        $primaryLinked = false;

        $primaryGuest = [
            'first_name' => $payload['guest_first_name'] ?? null,
            'last_name' => $payload['guest_last_name'] ?? null,
            'middle_initial' => $payload['guest_middle_initial'] ?? null,
            'gender' => $payload['guest_gender'] ?? null,
            'full_address' => $payload['guest_full_address'] ?? null,
            'contact_number' => $payload['guest_contact_number'] ?? null,
        ];

        $entries = $this->normalizeEntriesWithPrimaryGuest(
            $entries,
            $primaryGuest,
            (bool) ($payload['include_primary_in_first_room'] ?? true)
        );

        DB::transaction(function () use (
            $reservation,
            $payload,
            &$entries,
            $useHeldLocks,
            &$checkedInCount,
            &$maleCount,
            &$femaleCount,
            &$failedGuests,
            &$roomErrors,
            &$allSucceeded,
            &$primaryLinked,
            $primaryGuest
        ): void {
            $checkInAt = $payload['detailed_checkin_datetime'] ?? now();
            $checkOutAt = $payload['detailed_checkout_datetime'] ?? $reservation->check_out_date;

            foreach ($entries as $entryIndex => $entry) {
                $mode = $entry['room_mode'] ?? 'dorm';
                $roomId = $entry['room_id'] ?? null;
                $room = $roomId ? Room::query()
                    ->where('id', $roomId)
                    ->where('is_active', true)
                    ->when(
                        ! $useHeldLocks,
                        fn ($query) => $query->where('status', 'available'),
                        fn ($query) => $query->whereIn('status', ['available', 'reserved'])
                    )
                    ->first() : null;

                if (! $room) {
                    $allSucceeded = false;
                    $roomErrors[] = "No available room for entry #" . ($entryIndex + 1) . '.';
                    continue;
                }

                $entryGuests = $entry['guests'] ?? [];
                if (empty($entryGuests)) {
                    $allSucceeded = false;
                    $roomErrors[] = "No guests provided for room entry #" . ($entryIndex + 1) . '.';
                    continue;
                }

                if ($mode === 'private') {
                    $privateCheckedInCount = 0;

                    foreach ($entryGuests as $guest) {
                        $isPrimary = (bool) ($guest['_is_primary'] ?? false);
                        if (! $this->isGuestGenderAllowed($guest['gender'] ?? null, $room->gender_type)) {
                            $allSucceeded = false;
                            $guestName = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                            $failedGuests[] = "Gender mismatch for guest {$guestName} in room {$room->room_number}.";
                            continue;
                        }

                        $assignment = $this->createAssignment(
                            reservation: $reservation,
                            room: $room,
                            bed: null,
                            guestData: $guest,
                            payload: $payload,
                            checkInAt: $checkInAt,
                            checkOutAt: $checkOutAt,
                            includePayment: ! $primaryLinked && ($isPrimary || $checkedInCount === 0)
                        );

                        $checkedInCount++;
                        $privateCheckedInCount++;
                        if ($assignment->guest_gender === 'Male') {
                            $maleCount++;
                        }
                        if ($assignment->guest_gender === 'Female') {
                            $femaleCount++;
                        }

                        if ($isPrimary) {
                            $primaryLinked = true;
                        }
                    }

                    if ($privateCheckedInCount > 0) {
                        $room->update(['status' => 'occupied']);
                    }

                    continue;
                }

                // Dorm mode: assign first available bed for each guest.
                foreach ($entryGuests as $guest) {
                    if (! $this->isGuestGenderAllowed($guest['gender'] ?? null, $room->gender_type)) {
                        $allSucceeded = false;
                        $guestName = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                        $failedGuests[] = "Gender mismatch for guest {$guestName} in room {$room->room_number}.";
                        continue;
                    }

                    $bed = null;
                    if ($useHeldLocks) {
                        $heldBedId = $guest['bed_id'] ?? null;
                        $bed = $heldBedId
                            ? Bed::query()
                                ->where('id', $heldBedId)
                                ->where('room_id', $room->id)
                                ->whereIn('status', ['available', 'reserved'])
                                ->first()
                            : null;
                    } else {
                        $bed = Bed::query()
                            ->where('room_id', $room->id)
                            ->where('status', 'available')
                            ->orderBy('bed_number')
                            ->first();
                    }

                    if (! $bed) {
                        $allSucceeded = false;
                        $guestName = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                        $failedGuests[] = "No available bed for guest {$guestName} in room {$room->room_number}.";
                        continue;
                    }

                    $assignment = $this->createAssignment(
                        reservation: $reservation,
                        room: $room,
                        bed: $bed,
                        guestData: $guest,
                        payload: $payload,
                        checkInAt: $checkInAt,
                        checkOutAt: $checkOutAt,
                        includePayment: ! $primaryLinked && ((bool) ($guest['_is_primary'] ?? false) || $checkedInCount === 0)
                    );

                    $bed->update(['status' => 'occupied']);
                    $checkedInCount++;
                    if ($assignment->guest_gender === 'Male') {
                        $maleCount++;
                    }
                    if ($assignment->guest_gender === 'Female') {
                        $femaleCount++;
                    }

                    if (($guest['_is_primary'] ?? false) === true) {
                        $primaryLinked = true;
                    }
                }

                if ($room->beds()->where('status', 'available')->doesntExist()) {
                    $room->update(['status' => 'occupied']);
                }
            }

            if ($allSucceeded && ($checkedInCount > 0)) {
                $reservation->update([
                    'status' => 'checked_in',
                    'guest_gender' => $payload['guest_gender'] ?? $reservation->guest_gender,
                    'number_of_occupants' => $checkedInCount,
                    'num_male_guests' => $maleCount,
                    'num_female_guests' => $femaleCount,
                ]);
            }
        });

        return [
            'checked_in_count' => $checkedInCount,
            'failed_guests' => $failedGuests,
            'room_errors' => $roomErrors,
            'all_succeeded' => $allSucceeded,
        ];
    }

    /**
     * Prepare a reservation for payment by locking selected room/bed inventory.
     */
    public function preparePendingPayment(Reservation $reservation, array $payload): array
    {
        $this->releaseExpiredHolds();

        if (! in_array($reservation->status, ['approved', 'pending_payment'], true)) {
            throw new \RuntimeException('Only approved reservations can be prepared for payment.');
        }

        if ($reservation->status === 'pending_payment') {
            $this->releasePendingPaymentHold($reservation, true);
            $reservation->refresh();
        }

        $entries = $payload['reservation_rooms'] ?? [];
        if (empty($entries)) {
            throw new \RuntimeException('Please add at least one room entry before preparing payment.');
        }

        $primaryGuest = [
            'first_name' => $payload['guest_first_name'] ?? null,
            'last_name' => $payload['guest_last_name'] ?? null,
            'middle_initial' => $payload['guest_middle_initial'] ?? null,
            'gender' => $payload['guest_gender'] ?? null,
            'full_address' => $payload['guest_full_address'] ?? null,
            'contact_number' => $payload['guest_contact_number'] ?? null,
        ];

        $entries = $this->normalizeEntriesWithPrimaryGuest(
            $entries,
            $primaryGuest,
            (bool) ($payload['include_primary_in_first_room'] ?? true)
        );

        $holdEntries = [];

        DB::transaction(function () use ($reservation, $payload, $entries, &$holdEntries) {
            foreach ($entries as $entryIndex => $entry) {
                $mode = $entry['room_mode'] ?? 'dorm';
                $roomId = $entry['room_id'] ?? null;
                $room = $roomId
                    ? Room::query()->where('id', $roomId)->where('is_active', true)->first()
                    : null;

                if (! $room) {
                    throw new \RuntimeException('No available room found for entry #' . ($entryIndex + 1) . '.');
                }

                if ($room->status !== 'available') {
                    throw new \RuntimeException("Room {$room->room_number} is no longer available.");
                }

                $entryGuests = collect($entry['guests'] ?? [])
                    ->filter(fn ($guest) => filled($guest['first_name'] ?? null) || filled($guest['last_name'] ?? null))
                    ->values()
                    ->all();

                if (empty($entryGuests)) {
                    throw new \RuntimeException('No guests provided for room entry #' . ($entryIndex + 1) . '.');
                }

                if ($mode === 'private') {
                    $holdGuests = [];
                    foreach ($entryGuests as $guest) {
                        if (! $this->isGuestGenderAllowed($guest['gender'] ?? null, $room->gender_type)) {
                            $guestName = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                            throw new \RuntimeException("Gender mismatch for guest {$guestName} in room {$room->room_number}.");
                        }

                        $holdGuests[] = [
                            'first_name' => $guest['first_name'] ?? null,
                            'last_name' => $guest['last_name'] ?? null,
                            'middle_initial' => $guest['middle_initial'] ?? null,
                            'gender' => $guest['gender'] ?? null,
                            'full_address' => $guest['full_address'] ?? null,
                            'contact_number' => $guest['contact_number'] ?? null,
                            '_is_primary' => (bool) ($guest['_is_primary'] ?? false),
                            'bed_id' => null,
                        ];
                    }

                    $room->update(['status' => 'reserved']);
                    $holdEntries[] = [
                        'room_mode' => $mode,
                        'room_id' => $room->id,
                        'guests' => $holdGuests,
                    ];
                    continue;
                }

                $holdGuests = [];
                foreach ($entryGuests as $guest) {
                    if (! $this->isGuestGenderAllowed($guest['gender'] ?? null, $room->gender_type)) {
                        $guestName = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                        throw new \RuntimeException("Gender mismatch for guest {$guestName} in room {$room->room_number}.");
                    }

                    $bed = Bed::query()
                        ->where('room_id', $room->id)
                        ->where('status', 'available')
                        ->orderBy('bed_number')
                        ->first();

                    if (! $bed) {
                        $guestName = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                        throw new \RuntimeException("No available bed for guest {$guestName} in room {$room->room_number}.");
                    }

                    $bed->update(['status' => 'reserved']);

                    $holdGuests[] = [
                        'first_name' => $guest['first_name'] ?? null,
                        'last_name' => $guest['last_name'] ?? null,
                        'middle_initial' => $guest['middle_initial'] ?? null,
                        'gender' => $guest['gender'] ?? null,
                        'full_address' => $guest['full_address'] ?? null,
                        'contact_number' => $guest['contact_number'] ?? null,
                        '_is_primary' => (bool) ($guest['_is_primary'] ?? false),
                        'bed_id' => $bed->id,
                    ];
                }

                $holdEntries[] = [
                    'room_mode' => $mode,
                    'room_id' => $room->id,
                    'guests' => $holdGuests,
                ];
            }

            $reservation->update([
                'status' => 'pending_payment',
                'checkin_hold_payload' => [
                    'payload' => $payload,
                    'entries' => $holdEntries,
                ],
                'checkin_hold_started_at' => now(),
                'checkin_hold_expires_at' => now()->addMinutes(30),
                'checkin_hold_by' => auth()->id(),
            ]);
        });

        return [
            'held_room_count' => count($holdEntries),
            'held_guest_count' => collect($holdEntries)->sum(fn ($entry) => count($entry['guests'] ?? [])),
            'hold_expires_at' => $reservation->fresh()->checkin_hold_expires_at,
        ];
    }

    /**
     * Finalize check-in from a pending-payment hold.
     */
    public function finalizePendingPayment(Reservation $reservation, array $paymentData): array
    {
        $this->releaseExpiredHolds();

        if ($reservation->status !== 'pending_payment') {
            throw new \RuntimeException('Reservation is not in pending payment state.');
        }

        if (! $reservation->checkin_hold_payload) {
            throw new \RuntimeException('No pending hold data found for this reservation.');
        }

        $expiresAt = $reservation->checkin_hold_expires_at;
        if ($expiresAt && Carbon::parse($expiresAt)->isPast()) {
            $this->releasePendingPaymentHold($reservation, true);
            throw new \RuntimeException('Payment hold expired and has been released. Please prepare check-in again.');
        }

        $holdPayload = $reservation->checkin_hold_payload;
        $payload = $holdPayload['payload'] ?? [];
        $payload['reservation_rooms'] = $holdPayload['entries'] ?? [];
        $payload = array_merge($payload, $paymentData);

        $result = $this->execute($reservation, $payload, ['use_held_locks' => true]);

        if (($result['all_succeeded'] ?? false) === true) {
            $reservation->update([
                'checkin_hold_payload' => null,
                'checkin_hold_started_at' => null,
                'checkin_hold_expires_at' => null,
                'checkin_hold_by' => null,
            ]);
        }

        return $result;
    }

    /**
     * Release reserved inventory for a pending payment hold.
     */
    public function releasePendingPaymentHold(Reservation $reservation, bool $setApproved = false): void
    {
        $holdPayload = $reservation->checkin_hold_payload ?? [];
        $entries = $holdPayload['entries'] ?? [];

        DB::transaction(function () use ($entries) {
            foreach ($entries as $entry) {
                $mode = $entry['room_mode'] ?? 'dorm';

                if ($mode === 'private') {
                    $roomId = $entry['room_id'] ?? null;
                    if ($roomId) {
                        Room::query()
                            ->where('id', $roomId)
                            ->where('status', 'reserved')
                            ->update(['status' => 'available']);
                    }
                    continue;
                }

                foreach ($entry['guests'] ?? [] as $guest) {
                    $bedId = $guest['bed_id'] ?? null;
                    if (! $bedId) {
                        continue;
                    }

                    Bed::query()
                        ->where('id', $bedId)
                        ->where('status', 'reserved')
                        ->update(['status' => 'available']);
                }
            }
        });

        $update = [
            'checkin_hold_payload' => null,
            'checkin_hold_started_at' => null,
            'checkin_hold_expires_at' => null,
            'checkin_hold_by' => null,
        ];

        if ($setApproved) {
            $update['status'] = 'approved';
        }

        $reservation->update($update);
    }

    /**
     * Release all expired pending-payment holds and return affected count.
     */
    public function releaseExpiredHolds(): int
    {
        $expiredReservations = Reservation::query()
            ->where('status', 'pending_payment')
            ->whereNotNull('checkin_hold_expires_at')
            ->where('checkin_hold_expires_at', '<', now())
            ->get();

        foreach ($expiredReservations as $expiredReservation) {
            $this->releasePendingPaymentHold($expiredReservation, true);
        }

        return $expiredReservations->count();
    }

    /**
     * @param  array<string,mixed>  $guestData
     * @param  array<string,mixed>  $payload
     */
    private function createAssignment(
        Reservation $reservation,
        Room $room,
        ?Bed $bed,
        array $guestData,
        array $payload,
        mixed $checkInAt,
        mixed $checkOutAt,
        bool $includePayment
    ): RoomAssignment {
        $fullName = trim(
            ($guestData['first_name'] ?? '') . ' ' .
            (($guestData['middle_initial'] ?? '') ? ($guestData['middle_initial'] . ' ') : '') .
            ($guestData['last_name'] ?? '')
        );

        $guest = Guest::firstOrCreate([
            'reservation_id' => $reservation->id,
            'first_name' => $guestData['first_name'] ?? null,
            'last_name' => $guestData['last_name'] ?? null,
            'middle_initial' => $guestData['middle_initial'] ?? null,
            'gender' => $guestData['gender'] ?? null,
        ], [
            'full_name' => $fullName,
            'contact_number' => $guestData['contact_number'] ?? null,
            'notes' => null,
        ]);

        return RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'bed_id' => $bed?->id,
            'status' => 'checked_in',
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
            'checked_in_at' => $checkInAt,
            'checked_in_by' => auth()->id(),
            'remarks' => $payload['remarks'] ?? null,
            'guest_last_name' => $guestData['last_name'] ?? null,
            'guest_first_name' => $guestData['first_name'] ?? null,
            'guest_middle_initial' => $guestData['middle_initial'] ?? null,
            'guest_gender' => $guestData['gender'] ?? null,
            'guest_full_address' => $guestData['full_address'] ?? null,
            'guest_contact_number' => $guestData['contact_number'] ?? null,
            'id_type' => $includePayment ? ($payload['id_type'] ?? null) : null,
            'id_number' => $includePayment ? ($payload['id_number'] ?? null) : null,
            'nationality' => $includePayment ? ($payload['nationality'] ?? 'Filipino') : 'Filipino',
            'is_student' => $includePayment ? ($payload['is_student'] ?? false) : false,
            'is_senior_citizen' => $includePayment ? ($payload['is_senior_citizen'] ?? false) : false,
            'is_pwd' => $includePayment ? ($payload['is_pwd'] ?? false) : false,
            'purpose_of_stay' => $payload['purpose_of_stay'] ?? null,
            'detailed_checkin_datetime' => $checkInAt,
            'detailed_checkout_datetime' => $checkOutAt,
            'additional_requests' => $includePayment ? ($payload['additional_requests'] ?? null) : null,
            'payment_mode' => $includePayment ? ($payload['payment_mode'] ?? null) : null,
            'payment_mode_other' => $includePayment ? ($payload['payment_mode_other'] ?? null) : null,
            'payment_amount' => $includePayment ? ($payload['payment_amount'] ?? null) : null,
            'payment_or_number' => $includePayment ? ($payload['payment_or_number'] ?? null) : null,
            'notes' => $payload['remarks'] ?? null,
            'num_male_guests' => 0,
            'num_female_guests' => 0,
        ]);
    }

    private function isGuestGenderAllowed(?string $guestGender, string $roomGenderType): bool
    {
        if ($roomGenderType === 'any') {
            return true;
        }

        $normalizedGuestGender = strtolower((string) $guestGender);
        return $normalizedGuestGender === $roomGenderType;
    }

    /**
     * @param  array<int,array<string,mixed>>  $entries
     * @param  array<string,mixed>  $primaryGuest
     * @return array<int,array<string,mixed>>
     */
    private function normalizeEntriesWithPrimaryGuest(array $entries, array $primaryGuest, bool $fallbackToFirstRoom): array
    {
        if (empty($entries)) {
            return $entries;
        }

        $primaryIndices = [];
        foreach ($entries as $index => $entry) {
            if ((bool) ($entry['includes_primary_guest'] ?? false)) {
                $primaryIndices[] = $index;
            }
        }

        if (count($primaryIndices) > 1) {
            throw new \RuntimeException('Primary guest can only be included in one room entry.');
        }

        if (count($primaryIndices) === 0) {
            if (! $fallbackToFirstRoom) {
                throw new \RuntimeException('Please choose one room entry to include the primary guest.');
            }

            $primaryIndices = [0];
            $entries[0]['includes_primary_guest'] = true;
        }

        $primaryIndex = $primaryIndices[0];
        $entries[$primaryIndex]['guests'] = $entries[$primaryIndex]['guests'] ?? [];

        $hasPrimaryGuest = collect($entries[$primaryIndex]['guests'])
            ->contains(fn ($guest) => (bool) ($guest['_is_primary'] ?? false));

        if (! $hasPrimaryGuest) {
            array_unshift($entries[$primaryIndex]['guests'], [
                'first_name' => $primaryGuest['first_name'],
                'last_name' => $primaryGuest['last_name'],
                'middle_initial' => $primaryGuest['middle_initial'],
                'gender' => $primaryGuest['gender'],
                'full_address' => $primaryGuest['full_address'],
                'contact_number' => $primaryGuest['contact_number'],
                '_is_primary' => true,
            ]);
        }

        return $entries;
    }
}
