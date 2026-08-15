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
use App\Models\RoomHold;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    /**
     * Execute direct-entry check-in for one reservation across multiple room entries.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function execute(Reservation $reservation, array $payload, array $options = []): array
    {
        $entries = $payload['reservation_rooms'] ?? [];
        $useHeldLocks = (bool) ($options['use_held_locks'] ?? false);
        $atomic = (bool) ($options['atomic'] ?? false);

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
            $atomic,
            &$checkedInCount,
            &$maleCount,
            &$femaleCount,
            &$failedGuests,
            &$roomErrors,
            &$allSucceeded,
            &$primaryLinked

        ): void {
            $checkInAt = $payload['detailed_checkin_datetime'] ?? now();
            $checkOutAt = $payload['detailed_checkout_datetime'] ?? $reservation->check_out_date;

            // A normal check-in must consume this reservation's active holds exactly.
            // Reserved rooms are therefore usable by their own reservation, but never by
            // another reservation that happens to be checking in at the same time.
            $heldRoomIds = [];
            if ($useHeldLocks) {
                $heldRoomIds = RoomHold::query()
                    ->where('reservation_id', $reservation->id)
                    ->where('hold_type', 'advance')
                    ->active()
                    ->lockForUpdate()
                    ->pluck('room_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                $entryRoomIds = collect($entries)
                    ->pluck('room_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                if ($heldRoomIds !== $entryRoomIds) {
                    throw new \RuntimeException('The selected rooms no longer match this reservation\'s active room holds. Refresh the reservation and try again.');
                }
            }

            $fail = function (string $message) use (&$allSucceeded, &$roomErrors, $atomic): void {
                if ($atomic) {
                    throw new \RuntimeException($message);
                }

                $allSucceeded = false;
                $roomErrors[] = $message;
            };

            foreach ($entries as $entryIndex => $entry) {
                $mode = $entry['room_mode'] ?? 'dorm';
                $roomId = $entry['room_id'] ?? null;
                $room = $roomId ? Room::query()
                    ->where('id', $roomId)
                    ->where('is_active', true)
                    ->when(
                        $useHeldLocks,
                        fn ($query) => $query->whereIn('status', ['available', 'reserved']),
                        fn ($query) => $mode === 'dorm'
                            ? $query->whereIn('status', ['available', 'occupied'])
                            : $query->where('status', 'available')
                    )
                    ->lockForUpdate()
                    ->first() : null;

                if (! $room) {
                    $fail('No available room for entry #'.($entryIndex + 1).'.');

                    continue;
                }

                // For dorm rooms, also verify the room has available capacity
                if (! $useHeldLocks && $mode === 'dorm') {
                    $room->loadMissing('roomType');
                    if ($room->isFull()) {
                        $fail("Room {$room->room_number} has no available slots (capacity: {$room->capacity}).");

                        continue;
                    }
                }

                $entryGuests = $entry['guests'] ?? [];
                if (empty($entryGuests)) {
                    $fail('No guests provided for room entry #'.($entryIndex + 1).'.');

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
                        $guestName = trim(($guest['first_name'] ?? '').' '.($guest['last_name'] ?? ''));
                        $message = "No available slot for guest {$guestName} in room {$room->room_number} (capacity reached).";
                        if ($atomic) {
                            throw new \RuntimeException($message);
                        }
                        $allSucceeded = false;
                        $failedGuests[] = $message;

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

                // Clear all room holds since check-in is complete
                // This includes any advance holds that weren't used (e.g., staff changed the room)
                $holdsToRelease = RoomHold::query()
                    ->where('reservation_id', $reservation->id)
                    ->get();
                $deletedHolds = $holdsToRelease->count();
                RoomHold::query()->whereKey($holdsToRelease->pluck('id'))->delete();
                app(RoomHoldService::class)->recalculateRoomStatuses(
                    $holdsToRelease->pluck('room_id')->merge($reservation->roomAssignments()->pluck('room_id')),
                );

                if ($deletedHolds > 0) {
                    ReservationLog::record(
                        $reservation,
                        'room_holds_released',
                        "Released {$deletedHolds} room hold(s) after successful check-in.",
                        ['deleted_holds' => $deletedHolds]
                    );
                }
            } elseif (! $atomic) {
                // If check-in partially succeeded or failed, release advance holds for rooms that weren't actually assigned
                // This prevents unused held rooms from staying locked
                $assignedRoomIds = $reservation->roomAssignments()
                    ->where('status', 'checked_in')
                    ->pluck('room_id')
                    ->unique()
                    ->toArray();

                $holdsToRelease = RoomHold::query()
                    ->where('reservation_id', $reservation->id)
                    ->where('hold_type', 'advance')
                    ->whereNotIn('room_id', $assignedRoomIds)
                    ->get();
                $deletedHolds = $holdsToRelease->count();
                RoomHold::query()->whereKey($holdsToRelease->pluck('id'))->delete();
                app(RoomHoldService::class)->recalculateRoomStatuses(
                    $holdsToRelease->pluck('room_id')->merge($assignedRoomIds),
                );

                if ($deletedHolds > 0) {
                    ReservationLog::record(
                        $reservation,
                        'room_holds_released',
                        "Released {$deletedHolds} unused advance hold(s) after partial check-in.",
                        ['deleted_holds' => $deletedHolds, 'assigned_rooms' => $assignedRoomIds]
                    );
                }
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
     * Complete onsite reception check-in in one step.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function completeOnsiteCheckIn(Reservation $reservation, array $payload): array
    {
        if (! in_array($reservation->status, ['approved', 'confirmed'], true)) {
            throw new \RuntimeException('Only approved or confirmed reservations can be checked in.');
        }

        $entries = $payload['reservation_rooms'] ?? [];
        if (empty($entries)) {
            throw new \RuntimeException('Please add at least one room entry before check-in.');
        }

        $this->validateCompanionGuests($entries);

        $primaryGuest = [
            'first_name' => $payload['guest_first_name'] ?? null,
            'last_name' => $payload['guest_last_name'] ?? null,
            'middle_initial' => $payload['guest_middle_initial'] ?? null,
            'gender' => $payload['guest_gender'] ?? null,
            'age' => $payload['guest_age'] ?? null,
            'full_address' => $payload['guest_full_address'] ?? null,
            'contact_number' => $payload['guest_contact_number'] ?? null,
        ];

        $payload['reservation_rooms'] = $this->normalizeEntriesWithPrimaryGuest(
            $entries,
            $primaryGuest,
            false
        );

        $payableAmount = $this->computePayloadPayableAmount($reservation, $payload);
        $payload = array_merge(
            $payload,
            $this->validateAndNormalizeFinalizePaymentData($payload, $payableAmount)
        );

        $hasActiveAdvanceHolds = $reservation->roomHolds()
            ->where('hold_type', 'advance')
            ->active()
            ->exists();

        // Reception check-in is all-or-nothing.  If the reservation owns active
        // advance holds, its reserved rooms are the only rooms it may consume.
        $result = $this->execute($reservation, $payload, [
            'use_held_locks' => $hasActiveAdvanceHolds,
            'atomic' => true,
        ]);

        if (($result['all_succeeded'] ?? false) === true) {
            $reservation->refresh();
            app(ReservationAccountLinker::class)->link($reservation);
            $this->persistCheckInSnapshot($reservation, $payload);
            $this->persistFinancialRecords($reservation, $payload);

            ReservationLog::record(
                $reservation,
                'checkin_completed',
                'Onsite check-in completed. '.$result['checked_in_count'].' guest(s) checked in.'
                    .' Payment: PHP '.number_format((float) ($payload['payment_amount'] ?? 0), 2)
                    .' via '.strtoupper($payload['payment_mode'] ?? 'N/A')
                    .' (OR: '.($payload['payment_or_number'] ?? 'N/A').').',
                [
                    'checked_in_count' => $result['checked_in_count'],
                    'payment_amount' => $payload['payment_amount'] ?? null,
                    'payment_mode' => $payload['payment_mode'] ?? null,
                    'or_number' => $payload['payment_or_number'] ?? null,
                ]
            );
        }

        return $result;
    }

    /**
     * Compute expected payable amount from room entries and selected add-ons.
     *
     * @param  array<string,mixed>  $payload
     */
    private function computePayloadPayableAmount(Reservation $reservation, array $payload): float
    {
        $entries = $payload['reservation_rooms'] ?? [];

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

            $guestCount = count($entry['guests'] ?? []);

            if ($roomMode === 'dorm') {
                $roomSubtotal += $rate * max(1, $guestCount) * $nights;
            } else {
                $roomSubtotal += $rate * $nights;
            }
        }

        $additionalRequests = collect($payload['additional_requests'] ?? [])
            ->filter(fn ($i) => is_array($i) && ! empty($i['code'] ?? null));
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
        $grossTotal = max(0, $subtotal - $discountInfo['amount']);

        // Subtract existing posted payments (e.g. online deposits) from payable
        $existingPayments = (float) $reservation->payments()
            ->where('status', 'posted')
            ->sum('amount');

        return round(max(0, $grossTotal - $existingPayments), 2);
    }

    /**
     * Validate and normalize payment data for check-in.
     *
     * @param  array<string,mixed>  $paymentData
     * @return array<string,mixed>
     */
    private function validateAndNormalizeFinalizePaymentData(array $paymentData, float $payableAmount): array
    {
        // If no payment is required (fully paid online), skip payment validation
        if ($payableAmount <= 0.01) {
            return [
                'payment_mode' => 'online',
                'payment_amount' => 0.00,
                'payment_or_number' => filled($paymentData['payment_or_number'] ?? null)
                    ? trim((string) $paymentData['payment_or_number'])
                    : null,
                'or_date' => $paymentData['or_date'] ?? now()->toDateString(),
                'remarks' => $paymentData['remarks'] ?? null,
            ];
        }

        $paymentMode = strtolower(trim((string) ($paymentData['payment_mode'] ?? '')));
        if ($paymentMode === '') {
            throw new \RuntimeException('Mode of payment is required to complete check-in.');
        }

        if ($paymentMode === 'others' && blank($paymentData['payment_mode_other'] ?? null)) {
            throw new \RuntimeException('Please specify the payment mode when selecting Others.');
        }

        if (! array_key_exists('payment_amount', $paymentData)) {
            throw new \RuntimeException('Paid amount is required to complete check-in.');
        }

        $paidAmount = (float) $paymentData['payment_amount'];
        if ($paidAmount < 0) {
            throw new \RuntimeException('Paid amount cannot be negative.');
        }

        if ($payableAmount > 0 && $paidAmount + 0.00001 < $payableAmount) {
            throw new \RuntimeException('Paid amount cannot be less than the payable amount of PHP '.number_format($payableAmount, 2).'.');
        }

        if (blank($paymentData['payment_or_number'] ?? null)) {
            throw new \RuntimeException('Official receipt number is required to complete check-in.');
        }

        $paymentData['payment_mode'] = $paymentMode;
        $paymentData['payment_amount'] = round($paidAmount, 2);
        $paymentData['payment_or_number'] = trim((string) $paymentData['payment_or_number']);

        return $paymentData;
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
        $guest = Guest::firstOrCreate([
            'reservation_id' => $reservation->id,
            'first_name' => $guestData['first_name'] ?? null,
            'last_name' => $guestData['last_name'] ?? null,
            'middle_initial' => $guestData['middle_initial'] ?? null,
            'gender' => $guestData['gender'] ?? null,
        ], [
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
        // $payload already contains the completed check-in data: reservation rooms,
        // discount flags, and datetime fields.
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

            $guestCount = count($entry['guests'] ?? []);

            if ($roomMode === 'dorm') {
                $roomChargesBeforeDiscount += $rate * max(1, $guestCount) * $nights;
            } else {
                $roomChargesBeforeDiscount += $rate * $nights;
            }
        }

        $additionalRequestItems = collect($payload['additional_requests'] ?? [])
            ->filter(fn ($i) => is_array($i) && ! empty($i['code'] ?? null));
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
        // Preserve gateway (online deposit) payments — only delete non-gateway payments.
        $reservation->charges()->delete();
        $reservation->payments()
            ->where(function ($query) {
                $query->whereNull('gateway')->orWhere('gateway', '');
            })
            ->delete();

        // Store room charges (before discount)
        if ($roomChargesBeforeDiscount > 0) {
            ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'charge_type' => 'room_rate',
                'scope_type' => 'reservation',
                'scope_id' => $reservation->id,
                'description' => "Room charges ({$nights} night".($nights > 1 ? 's' : '').')',
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
            if (! $addon) {
                continue;
            }
            $price = (float) $addon->price;
            $amount = $price * $qty;
            ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'charge_type' => 'addon',
                'scope_type' => 'reservation',
                'scope_id' => $reservation->id,
                'description' => ($qty > 1 ? "{$qty}x " : '').$addon->name,
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

        if ($paymentAmount > 0.01) {
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
        }

        $reservation->refreshFinancialSummary();
    }

    /**
     * Calculate discount based on guest flags and settings
     *
     * @param  array  $payload  Check-in payload with guest flags
     * @param  float  $subtotal  Subtotal before discount (room charges + add-ons)
     * @return array ['amount' => float, 'percent' => float, 'types' => array, 'description' => string, 'subtotal' => float]
     */
    private function calculateDiscount(array $payload, float $subtotal): array
    {
        $isPwd = (bool) ($payload['is_pwd'] ?? false);
        $isSenior = (bool) ($payload['is_senior_citizen'] ?? false);
        $isStudent = (bool) ($payload['is_student'] ?? false);

        $pwdPercent = (float) Setting::get('discount_pwd_percent', 0);
        $seniorPercent = (float) Setting::get('discount_senior_percent', 0);
        $studentPercent = (float) Setting::get('discount_student_percent', 0);

        // Collect all applicable discounts and apply only the highest one
        $candidates = [];

        if ($isPwd && $pwdPercent > 0) {
            $candidates[] = ['label' => "PWD ({$pwdPercent}%)", 'percent' => $pwdPercent];
        }

        if ($isSenior && $seniorPercent > 0) {
            $candidates[] = ['label' => "Senior Citizen ({$seniorPercent}%)", 'percent' => $seniorPercent];
        }

        if ($isStudent && $studentPercent > 0) {
            $candidates[] = ['label' => "Student ({$studentPercent}%)", 'percent' => $studentPercent];
        }

        // Pick only the highest discount
        $applicableDiscounts = [];
        $totalPercent = 0;

        if (! empty($candidates)) {
            usort($candidates, fn ($a, $b) => $b['percent'] <=> $a['percent']);
            $best = $candidates[0];
            $applicableDiscounts[] = $best['label'];
            $totalPercent = $best['percent'];
        }

        $totalPercent = min($totalPercent, 100);

        $discountAmount = ($subtotal * $totalPercent) / 100;

        $description = empty($applicableDiscounts)
            ? 'No discount'
            : 'Discount: '.implode(' + ', $applicableDiscounts);

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
     */
    private function validateCompanionGuests(array $entries): void
    {
        foreach ($entries as $entryIndex => $entry) {
            foreach (($entry['guests'] ?? []) as $guestIndex => $guest) {
                if (
                    blank($guest['first_name'] ?? null)
                    || blank($guest['last_name'] ?? null)
                    || blank($guest['gender'] ?? null)
                ) {
                    throw new \RuntimeException(
                        'Complete companion guest details for room entry #'.($entryIndex + 1).', guest #'.($guestIndex + 1).'.'
                    );
                }
            }
        }
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
