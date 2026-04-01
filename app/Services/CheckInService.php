<?php

namespace App\Services;

use App\Models\CheckInSnapshot;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationLog;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Service;
use App\Models\Setting;
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
            'age' => $payload['guest_age'] ?? null,
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

                        $assignment = $this->createAssignment(
                            reservation: $reservation,
                            room: $room,
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

                // Dorm mode: assign each guest a slot based on capacity.
                foreach ($entryGuests as $guest) {
                    // Check capacity before assigning
                    $currentOccupancy = $room->roomAssignments()->where('status', 'checked_in')->count();
                    if ($room->capacity > 0 && $currentOccupancy >= $room->capacity) {
                        $allSucceeded = false;
                        $guestName = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                        $failedGuests[] = "No available slot for guest {$guestName} in room {$room->room_number} (capacity reached).";
                        continue;
                    }

                    $assignment = $this->createAssignment(
                        reservation: $reservation,
                        room: $room,
                        guestData: $guest,
                        payload: $payload,
                        checkInAt: $checkInAt,
                        checkOutAt: $checkOutAt,
                        includePayment: ! $primaryLinked && ((bool) ($guest['_is_primary'] ?? false) || $checkedInCount === 0)
                    );

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

                // Update room status based on current checked-in count
                $room->recalculateStatus();
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
            'age' => $payload['guest_age'] ?? null,
            'full_address' => $payload['guest_full_address'] ?? null,
            'contact_number' => $payload['guest_contact_number'] ?? null,
        ];

        $entries = $this->normalizeEntriesWithPrimaryGuest(
            $entries,
            $primaryGuest,
            (bool) ($payload['include_primary_in_first_room'] ?? true)
        );

        $holdPayloadData = $this->sanitizePreparePayload($payload);
        $holdMinutes = max(1, (int) ($payload['hold_duration_minutes'] ?? 180));

        $holdEntries = [];

        DB::transaction(function () use ($reservation, $holdPayloadData, $entries, &$holdEntries, $holdMinutes) {
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

                        $holdGuests[] = [
                            'first_name' => $guest['first_name'] ?? null,
                            'last_name' => $guest['last_name'] ?? null,
                            'middle_initial' => $guest['middle_initial'] ?? null,
                            'gender' => $guest['gender'] ?? null,
                            'age' => $guest['age'] ?? null,
                            'full_address' => $guest['full_address'] ?? null,
                            'contact_number' => $guest['contact_number'] ?? null,
                            '_is_primary' => (bool) ($guest['_is_primary'] ?? false),
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
                    $holdGuests[] = [
                        'first_name'    => $guest['first_name'] ?? null,
                        'last_name'     => $guest['last_name'] ?? null,
                        'middle_initial' => $guest['middle_initial'] ?? null,
                        'gender'        => $guest['gender'] ?? null,
                        'age'           => $guest['age'] ?? null,
                        'full_address'  => $guest['full_address'] ?? null,
                        'contact_number' => $guest['contact_number'] ?? null,
                        '_is_primary'   => (bool) ($guest['_is_primary'] ?? false),
                    ];
                }

                // Reserve the room slot (same as private — the room is held for this reservation)
                $room->update(['status' => 'reserved']);

                $holdEntries[] = [
                    'room_mode' => $mode,
                    'room_id'   => $room->id,
                    'guests'    => $holdGuests,
                ];
            }

            $reservation->update([
                'status' => 'pending_payment',
                'checkin_hold_payload' => [
                    'payload' => $holdPayloadData,
                    'entries' => $holdEntries,
                ],
                'checkin_hold_started_at' => now(),
                'checkin_hold_expires_at' => now()->addMinutes($holdMinutes),
                'checkin_hold_by' => auth()->id(),
            ]);
        });

        $heldGuestCount = collect($holdEntries)->sum(fn ($entry) => count($entry['guests'] ?? []));
        $expiresAt = $reservation->fresh()->checkin_hold_expires_at;

        ReservationLog::record(
            $reservation,
            'checkin_hold_prepared',
            'Check-in hold prepared for ' . $heldGuestCount . ' guest(s) across ' . count($holdEntries) . ' room(s). Hold expires at ' . $expiresAt?->format('M d, Y h:i A') . '.',
            [
                'held_room_count'  => count($holdEntries),
                'held_guest_count' => $heldGuestCount,
                'expires_at'       => $expiresAt?->toDateTimeString(),
            ]
        );

        return [
            'held_room_count' => count($holdEntries),
            'held_guest_count' => $heldGuestCount,
            'hold_expires_at' => $expiresAt,
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
        $payableAmount = $this->computeHoldPayableAmount($reservation, $holdPayload);
        $paymentData = $this->validateAndNormalizeFinalizePaymentData($paymentData, $payableAmount);
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

            $reservation->refresh();
            $this->persistCheckInSnapshot($reservation, $payload);
            $this->persistFinancialRecords($reservation, $payload);

            ReservationLog::record(
                $reservation,
                'checkin_finalized',
                'Check-in finalized. ' . $result['checked_in_count'] . ' guest(s) checked in.'
                    . ' Payment: PHP ' . number_format((float) ($payload['payment_amount'] ?? 0), 2)
                    . ' via ' . strtoupper($payload['payment_mode'] ?? 'N/A')
                    . ' (OR: ' . ($payload['payment_or_number'] ?? 'N/A') . ').',
                [
                    'checked_in_count' => $result['checked_in_count'],
                    'payment_amount'   => $payload['payment_amount'] ?? null,
                    'payment_mode'     => $payload['payment_mode'] ?? null,
                    'or_number'        => $payload['payment_or_number'] ?? null,
                ]
            );
        }

        return $result;
    }

    /**
     * Keep hold payload focused on assignment/schedule details only.
     * Payment capture happens at finalization.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function sanitizePreparePayload(array $payload): array
    {
        unset(
            $payload['payment_mode'],
            $payload['payment_mode_other'],
            $payload['payment_amount'],
            $payload['payment_or_number']
        );

        return $payload;
    }

    /**
     * Compute expected payable amount from held room entries and selected add-ons.
     *
     * @param  array<string,mixed>  $holdPayload
     */
    private function computeHoldPayableAmount(Reservation $reservation, array $holdPayload): float
    {
        $entries = $holdPayload['entries'] ?? [];
        $payload = $holdPayload['payload'] ?? [];

        if (! is_array($entries) || empty($entries)) {
            return (float) ($payload['payment_amount'] ?? 0);
        }

        $nights = max(1, Carbon::parse($reservation->check_in_date)->diffInDays(Carbon::parse($reservation->check_out_date)));

        $roomIds = collect($entries)
            ->pluck('room_id')
            ->filter()
            ->unique()
            ->values();

        $roomsById = Room::query()
            ->with('roomType')
            ->whereIn('id', $roomIds)
            ->get()
            ->keyBy('id');

        $roomSubtotal = 0.0;
        foreach ($entries as $entry) {
            $roomId = $entry['room_id'] ?? null;
            if (! $roomId || ! $roomsById->has($roomId)) {
                continue;
            }

            $room = $roomsById->get($roomId);
            $roomType = $room->roomType;
            $rate = (float) ($roomType->base_rate ?? 0);
            $roomMode = $entry['room_mode'] ?? ($roomType?->isPrivate() ? 'private' : 'dorm');

            $guestCount = collect($entry['guests'] ?? [])
                ->filter(fn ($guest) => filled($guest['first_name'] ?? null) || filled($guest['last_name'] ?? null))
                ->count();

            if ($roomMode === 'dorm') {
                $roomSubtotal += $rate * max(1, $guestCount) * $nights;
            } else {
                $roomSubtotal += $rate * $nights;
            }
        }

        $additionalRequests = collect($payload['additional_requests'] ?? [])
            ->filter(fn ($i) => is_array($i) && !empty($i['code'] ?? null));
        // Backward-compat: plain array of strings
        if ($additionalRequests->isEmpty()) {
            $legacyCodes = collect($payload['additional_requests'] ?? [])->filter(fn ($v) => is_string($v) && $v !== '');
            $additionalRequests = $legacyCodes->map(fn ($code) => ['code' => $code, 'qty' => 1]);
        }
        $addonsById = $additionalRequests->isEmpty()
            ? collect()
            : Service::query()->whereIn('code', $additionalRequests->pluck('code')->unique())->get()->keyBy('code');
        $servicesTotal = (float) $additionalRequests->sum(
            fn ($i) => (float) ($addonsById->get($i['code'])?->price ?? 0) * max(1, (int) ($i['qty'] ?? 1))
        );

        $subtotal = $roomSubtotal + $servicesTotal;

        // Apply discount
        $discountInfo = $this->calculateDiscount($payload, $subtotal);
        $finalAmount = max(0, $subtotal - $discountInfo['amount']);

        return round($finalAmount, 2);
    }

    /**
     * @param  array<string,mixed>  $paymentData
     * @return array<string,mixed>
     */
    private function validateAndNormalizeFinalizePaymentData(array $paymentData, float $payableAmount): array
    {
        $paymentMode = strtolower(trim((string) ($paymentData['payment_mode'] ?? '')));
        if ($paymentMode === '') {
            throw new \RuntimeException('Mode of payment is required to finalize check-in.');
        }

        if ($paymentMode === 'others' && blank($paymentData['payment_mode_other'] ?? null)) {
            throw new \RuntimeException('Please specify the payment mode when selecting Others.');
        }

        if (! array_key_exists('payment_amount', $paymentData)) {
            throw new \RuntimeException('Paid amount is required to finalize check-in.');
        }

        $paidAmount = (float) $paymentData['payment_amount'];
        if ($paidAmount < 0) {
            throw new \RuntimeException('Paid amount cannot be negative.');
        }

        if ($payableAmount > 0 && $paidAmount + 0.00001 < $payableAmount) {
            throw new \RuntimeException('Paid amount cannot be less than the payable amount of PHP ' . number_format($payableAmount, 2) . '.');
        }

        if (blank($paymentData['payment_or_number'] ?? null)) {
            throw new \RuntimeException('Official receipt number is required to finalize check-in.');
        }

        $paymentData['payment_mode'] = $paymentMode;
        $paymentData['payment_amount'] = round($paidAmount, 2);
        $paymentData['payment_or_number'] = trim((string) $paymentData['payment_or_number']);

        return $paymentData;
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
                $roomId = $entry['room_id'] ?? null;
                if ($roomId) {
                    Room::query()
                        ->where('id', $roomId)
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
            ReservationLog::record(
                $expiredReservation,
                'checkin_hold_expired',
                "Payment hold expired automatically for reservation #{$expiredReservation->reference_number}.",
                [],
                null,
                'System'
            );
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
            'age' => $guestData['age'] ?? null,
            'contact_number' => $guestData['contact_number'] ?? null,
            'notes' => null,
        ]);

        return RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'guest_id' => $guest->id,
            'room_id' => $room->id,
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
            'guest_age' => $guestData['age'] ?? null,
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
            'or_date' => $includePayment ? ($payload['or_date'] ?? null) : null,
            'notes' => $payload['remarks'] ?? null,
            'num_male_guests' => 0,
            'num_female_guests' => 0,
        ]);
    }

    private function persistCheckInSnapshot(Reservation $reservation, array $payload): void
    {
        $assignment = $reservation->roomAssignments()
            ->whereNotNull('payment_amount')
            ->latest('id')
            ->first();

        $billingGuestId = $assignment?->guest_id
            ?? $reservation->guests()->oldest('id')->value('id');

        if ($billingGuestId && ! $reservation->billing_guest_id) {
            $reservation->update(['billing_guest_id' => $billingGuestId]);
        }

        CheckInSnapshot::create([
            'reservation_id' => $reservation->id,
            'guest_id' => $billingGuestId,
            'id_type' => $payload['id_type'] ?? null,
            'id_number' => $payload['id_number'] ?? null,
            'nationality' => $payload['nationality'] ?? 'Filipino',
            'purpose_of_stay' => $payload['purpose_of_stay'] ?? null,
            'detailed_checkin_datetime' => $payload['detailed_checkin_datetime'] ?? null,
            'detailed_checkout_datetime' => $payload['detailed_checkout_datetime'] ?? null,
            'payment_mode' => $payload['payment_mode'] ?? null,
            'payment_amount' => $payload['payment_amount'] ?? null,
            'payment_or_number' => $payload['payment_or_number'] ?? null,
            'or_date' => $payload['or_date'] ?? null,
            'additional_requests' => $payload['additional_requests'] ?? null,
            'remarks' => $payload['remarks'] ?? null,
            'captured_by' => auth()->id(),
            'captured_at' => now(),
        ]);
    }

    private function persistFinancialRecords(Reservation $reservation, array $payload): void
    {
        // $payload already contains all hold data: reservation_rooms (from entries),
        // discount flags, and datetime fields. The hold payload was cleared before
        // this method is called, so we must NOT read from $reservation->checkin_hold_payload.
        $entries = $payload['reservation_rooms'] ?? [];
        $paymentAmount = (float) ($payload['payment_amount'] ?? 0);
        
        // Calculate room charges from the reservation dates
        $nights = max(1, Carbon::parse($reservation->check_in_date)->diffInDays(Carbon::parse($reservation->check_out_date)));

        $roomIds = collect($entries)
            ->pluck('room_id')
            ->filter()
            ->unique()
            ->values();

        $roomsById = Room::query()
            ->with('roomType')
            ->whereIn('id', $roomIds)
            ->get()
            ->keyBy('id');

        $roomChargesBeforeDiscount = 0.0;
        foreach ($entries as $entry) {
            $roomId = $entry['room_id'] ?? null;
            if (! $roomId || ! $roomsById->has($roomId)) {
                continue;
            }

            $room = $roomsById->get($roomId);
            $roomType = $room->roomType;
            $rate = (float) ($roomType->base_rate ?? 0);
            $roomMode = $entry['room_mode'] ?? ($roomType?->isPrivate() ? 'private' : 'dorm');

            $guestCount = collect($entry['guests'] ?? [])
                ->filter(fn ($guest) => filled($guest['first_name'] ?? null) || filled($guest['last_name'] ?? null))
                ->count();

            if ($roomMode === 'dorm') {
                $roomChargesBeforeDiscount += $rate * max(1, $guestCount) * $nights;
            } else {
                $roomChargesBeforeDiscount += $rate * $nights;
            }
        }

        $additionalRequestItems = collect($payload['additional_requests'] ?? [])
            ->filter(fn ($i) => is_array($i) && !empty($i['code'] ?? null));
        // Backward-compat: plain array of strings
        if ($additionalRequestItems->isEmpty()) {
            $legacyCodes = collect($payload['additional_requests'] ?? [])->filter(fn ($v) => is_string($v) && $v !== '');
            $additionalRequestItems = $legacyCodes->map(fn ($code) => ['code' => $code, 'qty' => 1]);
        }

        $addonsById = $additionalRequestItems->isEmpty()
            ? collect()
            : Service::query()->whereIn('code', $additionalRequestItems->pluck('code')->unique())->get(['code', 'name', 'price'])->keyBy('code');

        $addonsTotal = (float) $additionalRequestItems->sum(
            fn ($i) => (float) ($addonsById->get($i['code'])?->price ?? 0) * max(1, (int) ($i['qty'] ?? 1))
        );
        $subtotalBeforeDiscount = $roomChargesBeforeDiscount + $addonsTotal;
        
        // Calculate discount
        $discountInfo = $this->calculateDiscount($payload, $subtotalBeforeDiscount);
        $discountAmount = $discountInfo['amount'];

        // Clear existing ledger rows for this reservation to keep rollout idempotent.
        $reservation->charges()->delete();
        $reservation->payments()->delete();

        // Store room charges (before discount)
        if ($roomChargesBeforeDiscount > 0) {
            ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'charge_type' => 'room_rate',
                'scope_type' => 'reservation',
                'scope_id' => $reservation->id,
                'description' => "Room charges ({$nights} night" . ($nights > 1 ? 's' : '') . ")",
                'qty' => 1,
                'unit_price' => $roomChargesBeforeDiscount,
                'amount' => $roomChargesBeforeDiscount,
                'currency' => 'PHP',
                'meta' => [
                    'source' => 'checkin_finalize',
                    'nights' => $nights,
                ],
                'created_by' => auth()->id(),
            ]);
        }

        // Store addon charges
        foreach ($additionalRequestItems as $item) {
            $code = $item['code'];
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $addon = $addonsById->get($code);
            if (! $addon) continue;
            $price = (float) $addon->price;
            $amount = $price * $qty;
            ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'charge_type' => 'addon',
                'scope_type' => 'reservation',
                'scope_id' => $reservation->id,
                'description' => ($qty > 1 ? "{$qty}x " : '') . $addon->name,
                'qty' => $qty,
                'unit_price' => $price,
                'amount' => $amount,
                'currency' => 'PHP',
                'meta' => [
                    'source' => 'checkin_finalize',
                    'service_code' => $addon->code,
                    'qty' => $qty,
                ],
                'created_by' => auth()->id(),
            ]);
        }

        // Create discount charge if applicable (negative amount)
        if ($discountAmount > 0) {
            ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'charge_type' => 'discount',
                'scope_type' => 'reservation',
                'scope_id' => $reservation->id,
                'description' => $discountInfo['description'],
                'qty' => 1,
                'unit_price' => -$discountAmount,
                'amount' => -$discountAmount,
                'currency' => 'PHP',
                'meta' => [
                    'source' => 'checkin_finalize',
                    'discount_types' => $discountInfo['types'],
                    'discount_percent' => $discountInfo['percent'],
                    'subtotal_before_discount' => $discountInfo['subtotal'],
                ],
                'created_by' => auth()->id(),
            ]);
        }

        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => $paymentAmount,
            'payment_mode' => $payload['payment_mode'] ?? null,
            'reference_no' => $payload['payment_or_number'] ?? null,
            'or_date' => $payload['or_date'] ?? null,
            'status' => 'posted',
            'received_by' => auth()->id(),
            'received_at' => now(),
            'remarks' => $payload['remarks'] ?? null,
            'meta' => ['source' => 'checkin_finalize'],
        ]);

        $reservation->refreshFinancialSummary();
    }

    /**
     * Calculate discount based on guest flags and settings
     * 
     * @param array $payload Check-in payload with guest flags
     * @param float $subtotal Subtotal before discount (room charges + add-ons)
     * @return array ['amount' => float, 'percent' => float, 'types' => array, 'description' => string, 'subtotal' => float]
     */
    private function calculateDiscount(array $payload, float $subtotal): array
    {
        $isPwd = (bool) ($payload['is_pwd'] ?? false);
        $isSenior = (bool) ($payload['is_senior_citizen'] ?? false);
        $isStudent = (bool) ($payload['is_student'] ?? false);

        $pwdPercent     = (float) Setting::get('discount_pwd_percent', 0);
        $seniorPercent  = (float) Setting::get('discount_senior_percent', 0);
        $studentPercent = (float) Setting::get('discount_student_percent', 0);

        $applicableDiscounts = [];
        $totalPercent = 0;

        if ($isPwd && $pwdPercent > 0) {
            $applicableDiscounts[] = "PWD ({$pwdPercent}%)";
            $totalPercent += $pwdPercent;
        }

        if ($isSenior && $seniorPercent > 0) {
            $applicableDiscounts[] = "Senior Citizen ({$seniorPercent}%)";
            $totalPercent += $seniorPercent;
        }

        if ($isStudent && $studentPercent > 0) {
            $applicableDiscounts[] = "Student ({$studentPercent}%)";
            $totalPercent += $studentPercent;
        }

        // Cap total discount at 100%
        $totalPercent = min($totalPercent, 100);

        $discountAmount = ($subtotal * $totalPercent) / 100;

        $description = empty($applicableDiscounts)
            ? 'No discount'
            : 'Discount: ' . implode(' + ', $applicableDiscounts);

        return [
            'amount' => $discountAmount,
            'percent' => $totalPercent,
            'types' => $applicableDiscounts,
            'description' => $description,
            'subtotal' => $subtotal,
        ];
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
                'age' => $primaryGuest['age'] ?? null,
                'full_address' => $primaryGuest['full_address'],
                'contact_number' => $primaryGuest['contact_number'],
                '_is_primary' => true,
            ]);
        }

        return $entries;
    }
}
