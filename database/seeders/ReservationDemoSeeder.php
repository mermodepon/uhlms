<?php

namespace Database\Seeders;

use App\Models\CheckInSnapshot;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationLog;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class ReservationDemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20260429);

        $today = Carbon::today();
        $depositPercentage = (float) (Setting::query()->where('key', 'online_payment_deposit_percentage')->value('value') ?? 30);

        $users = User::query()->orderBy('id')->get();
        $staffPool = $users->whereIn('role', ['super_admin', 'admin', 'staff'])->values();
        $roomTypes = RoomType::query()
            ->with(['rooms' => fn ($query) => $query->where('is_active', true)->whereNotIn('status', ['maintenance', 'inactive'])])
            ->orderBy('id')
            ->get();
        $roomTypePool = $roomTypes
            ->flatMap(fn (RoomType $roomType) => array_fill(0, max(1, $roomType->rooms->count()), $roomType))
            ->values();
        $services = Service::query()->where('is_active', true)->orderBy('id')->get();

        $roomSchedules = [];
        $statusPlan = $this->buildStatusPlan();
        shuffle($statusPlan);

        $nameData = $this->nameData();

        foreach (range(1, 100) as $sequence) {
            $status = $statusPlan[$sequence - 1];
            /** @var RoomType $roomType */
            $roomType = $roomTypePool[($sequence - 1) % $roomTypePool->count()];
            $requiresAllocatedRoom = in_array($status, ['confirmed', 'checked_in', 'checked_out'], true);

            $reservationPayload = null;
            $guestPayloads = [];
            $financials = [];
            $addon = null;
            $allocatedRoom = null;
            $reviewer = null;

            for ($attempt = 0; $attempt < 40; $attempt++) {
                $occupants = $this->determineOccupants($roomType);
                $dateWindow = $this->buildDateWindow($status, $today, $sequence, $attempt);
                $guestPayloads = $this->buildGuestPayloads($occupants, $nameData, $sequence, $today);
                $financials = $this->buildFinancials($roomType, $status, $dateWindow['nights'], $occupants, $services, $depositPercentage);
                $reviewer = $status === 'pending'
                    ? null
                    : $staffPool[($sequence + $attempt) % $staffPool->count()];

                $allocatedRoom = $requiresAllocatedRoom
                    ? $this->findAvailableRoom($roomType, $dateWindow['check_in'], $dateWindow['check_out'], $occupants, $roomSchedules)
                    : null;

                if ($requiresAllocatedRoom && ! $allocatedRoom) {
                    continue;
                }

                $addon = $financials['addon'];
                $reservationPayload = $this->buildReservationPayload(
                    sequence: $sequence,
                    status: $status,
                    roomType: $roomType,
                    guestPayloads: $guestPayloads,
                    dateWindow: $dateWindow,
                    financials: $financials,
                    reviewer: $reviewer,
                    allocatedRoom: $allocatedRoom,
                    depositPercentage: $depositPercentage,
                    today: $today,
                );

                break;
            }

            if ($reservationPayload === null) {
                throw new RuntimeException("Unable to build a valid seeded reservation for slot {$sequence}.");
            }

            $reservation = Reservation::query()->create($reservationPayload);

            $createdGuests = collect();
            foreach ($guestPayloads as $index => $guestPayload) {
                $guest = Guest::query()->create(array_merge($guestPayload, [
                    'reservation_id' => $reservation->id,
                    'relationship_to_primary' => $index === 0 ? 'self' : $guestPayload['relationship_to_primary'],
                ]));
                $createdGuests->push($guest);
            }

            $primaryGuest = $createdGuests->first();
            $reservation->update(['billing_guest_id' => $primaryGuest?->id]);

            $this->seedChargesAndPayments($reservation, $financials, $reviewer);
            $this->seedLogs($reservation, $status, $reviewer, $reservationPayload, $allocatedRoom);

            if (in_array($status, ['confirmed'], true)) {
                $this->seedHold($reservation, $allocatedRoom, $status, $reviewer, $today);
                $this->rememberRoomUsage($roomSchedules, $allocatedRoom, $reservation->check_in_date, $reservation->check_out_date);
            }

            if (in_array($status, ['checked_in', 'checked_out'], true)) {
                $this->seedAssignmentsAndSnapshot(
                    reservation: $reservation,
                    roomType: $roomType,
                    guests: $createdGuests,
                    allocatedRoom: $allocatedRoom,
                    reviewer: $reviewer,
                    financials: $financials,
                    addon: $addon,
                    today: $today,
                );
                $this->rememberRoomUsage($roomSchedules, $allocatedRoom, $reservation->check_in_date, $reservation->check_out_date);
            }
        }
    }

    private function buildStatusPlan(): array
    {
        return array_merge(
            array_fill(0, 18, 'pending'),
            array_fill(0, 14, 'approved'),
            array_fill(0, 12, 'confirmed'),
            array_fill(0, 10, 'declined'),
            array_fill(0, 8, 'cancelled'),
            array_fill(0, 14, 'checked_in'),
            array_fill(0, 14, 'checked_out'),
        );
    }

    private function nameData(): array
    {
        return [
            'last_names' => [
                'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Mendoza', 'Garcia', 'Torres', 'Villanueva', 'Santiago',
                'Ramos', 'Castillo', 'Fernando', 'Dela Rosa', 'Navarro', 'Soriano', 'Mercado', 'Pascual',
                'Salvador', 'Hernandez', 'Diaz', 'Lopez', 'Gutierrez', 'Aguilar', 'Bernardo', 'Flores',
                'Morales', 'Rivera', 'Perez', 'Jimenez', 'Manalo', 'Tolentino', 'Espiritu', 'Velasco',
                'Cortez', 'Enriquez', 'De Leon', 'Macapagal', 'Magno', 'Fajardo', 'Galang', 'Lim', 'Tan',
                'Sy', 'Chua', 'Ong', 'Go', 'Yu', 'Ang', 'Co',
            ],
            'male_names' => [
                'Jose', 'Juan', 'Pedro', 'Carlos', 'Mario', 'Roberto', 'Eduardo', 'Ricardo', 'Fernando',
                'Miguel', 'Rafael', 'Gabriel', 'Antonio', 'Francisco', 'Luis', 'Ramon', 'Marco', 'Paolo',
                'Sergio', 'Angelo', 'Dennis', 'Mark', 'James', 'Bryan', 'Patrick', 'Kenneth', 'Jayson',
                'Rommel', 'Ariel', 'Rolando',
            ],
            'female_names' => [
                'Maria', 'Ana', 'Rosa', 'Lucia', 'Elena', 'Carmen', 'Patricia', 'Sophia', 'Isabela',
                'Victoria', 'Beatriz', 'Corazon', 'Dolores', 'Gloria', 'Luz', 'Rosario', 'Teresa',
                'Angela', 'Cristina', 'Diana', 'Grace', 'Joy', 'Karen', 'Michelle', 'Nicole', 'Vanessa',
                'Jasmine', 'Hannah', 'Celine', 'Bianca',
            ],
            'middle_initials' => ['A.', 'B.', 'C.', 'D.', 'E.', 'F.', 'G.', 'H.', 'L.', 'M.', 'N.', 'P.', 'R.', 'S.', 'T.', 'V.'],
            'addresses' => [
                'Quezon City', 'Manila', 'Cebu City', 'Davao City', 'Makati City', 'Pasig City', 'Taguig City',
                'Baguio City', 'Iloilo City', 'Cagayan de Oro', 'Zamboanga City', 'Bacolod City',
                'General Santos City', 'Antipolo City', 'Marikina City', 'Parañaque City', 'Las Piñas City',
                'Caloocan City', 'Muntinlupa City', 'San Juan City', 'Mandaluyong City', 'Pasay City',
                'Butuan City', 'Tacloban City', 'Legazpi City', 'Naga City', 'Angeles City',
            ],
            'purposes' => ['academic', 'official', 'personal', 'event', 'training', 'research'],
            'id_types' => ['National ID', "Driver's License", 'Passport', 'Student ID', 'Company ID', 'Government ID', 'Senior Citizen ID', 'PWD ID'],
            'relationships' => ['Spouse', 'Sibling', 'Child', 'Colleague', 'Friend', 'Relative'],
            'special_requests' => [
                'Need extra pillows', 'Late checkout requested', 'Quiet room preferred', 'Near stairway please',
                'Attending university conference', 'Student group from CMU', 'Require accessible room',
                'Celebrating anniversary', 'Will arrive late evening', 'Need parking space',
            ],
            'nationalities' => ['Filipino', 'Filipino', 'Filipino', 'Filipino', 'Filipino', 'American', 'Korean', 'Japanese', 'Chinese', 'Australian'],
            'payment_modes' => ['Cash', 'Card', 'Bank Transfer', 'GCash'],
        ];
    }

    private function determineOccupants(RoomType $roomType): int
    {
        $maxCapacity = max(1, (int) ($roomType->rooms->max('capacity') ?? 1));

        if ($roomType->room_sharing_type === 'public') {
            return min($maxCapacity, mt_rand(2, min(8, $maxCapacity)));
        }

        if (str_contains(strtolower($roomType->name), 'single')) {
            return 1;
        }

        $lowerBound = $maxCapacity >= 4 ? 2 : 1;

        return mt_rand($lowerBound, $maxCapacity);
    }

    private function buildDateWindow(string $status, Carbon $today, int $sequence, int $attempt): array
    {
        $seed = $sequence + $attempt;

        return match ($status) {
            'checked_out' => $this->window($today->copy()->subDays(10 + (($seed * 3) % 90)), 1 + ($seed % 4)),
            'checked_in' => $this->window($today->copy()->subDays($seed % 3), 2 + ($seed % 5)),
            'approved' => $this->window($today->copy()->addDays(5 + (($seed * 2) % 45)), 1 + ($seed % 4)),
            'confirmed' => $this->window($today->copy()->addDays($seed % 18), 2 + ($seed % 5)),

            'pending' => $this->window($today->copy()->addDays(7 + (($seed * 3) % 60)), 1 + ($seed % 5)),
            'declined', 'cancelled' => $this->window($today->copy()->addDays(-5 + ($seed % 35)), 1 + ($seed % 4)),
            default => $this->window($today->copy()->addDays(14), 2),
        };
    }

    private function window(Carbon $checkIn, int $nights): array
    {
        return [
            'check_in' => $checkIn->copy(),
            'check_out' => $checkIn->copy()->addDays($nights),
            'nights' => $nights,
        ];
    }

    private function buildGuestPayloads(int $occupants, array $nameData, int $sequence, Carbon $today): array
    {
        $lastName = $nameData['last_names'][($sequence - 1) % count($nameData['last_names'])];
        $middle = $nameData['middle_initials'][($sequence - 1) % count($nameData['middle_initials'])];
        $primaryMale = mt_rand(0, 1) === 1;
        $primaryFirst = $primaryMale
            ? $nameData['male_names'][($sequence * 3) % count($nameData['male_names'])]
            : $nameData['female_names'][($sequence * 3) % count($nameData['female_names'])];
        $primaryAge = mt_rand(21, 68);
        $phone = '09'.mt_rand(10, 99).str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        $idType = $nameData['id_types'][($sequence - 1) % count($nameData['id_types'])];

        $guests = [[
            'full_name' => "{$primaryFirst} {$middle} {$lastName}",
            'first_name' => $primaryFirst,
            'last_name' => $lastName,
            'middle_initial' => $middle,
            'relationship_to_primary' => 'self',
            'age' => $primaryAge,
            'gender' => $primaryMale ? 'Male' : 'Female',
            'contact_number' => $phone,
            'id_type' => $idType,
            'id_number' => 'ID-'.Carbon::today()->year.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'notes' => null,
        ]];

        for ($index = 2; $index <= $occupants; $index++) {
            $male = mt_rand(0, 1) === 1;
            $firstName = $male
                ? $nameData['male_names'][($sequence + $index) % count($nameData['male_names'])]
                : $nameData['female_names'][($sequence + $index) % count($nameData['female_names'])];

            $guests[] = [
                'full_name' => "{$firstName} {$lastName}",
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_initial' => null,
                'relationship_to_primary' => $nameData['relationships'][($sequence + $index) % count($nameData['relationships'])],
                'age' => mt_rand(12, 65),
                'gender' => $male ? 'Male' : 'Female',
                'contact_number' => null,
                'id_type' => null,
                'id_number' => null,
                'notes' => null,
            ];
        }

        return $guests;
    }

    private function buildFinancials(
        RoomType $roomType,
        string $status,
        int $nights,
        int $occupants,
        Collection $services,
        float $depositPercentage,
    ): array {
        $roomRate = $roomType->pricing_type === 'per_person'
            ? (float) $roomType->base_rate * $occupants * $nights
            : (float) $roomType->base_rate * $nights;
        $chargeQty = $roomType->pricing_type === 'per_person' ? $occupants * $nights : $nights;
        $chargeDescription = $roomType->pricing_type === 'per_person'
            ? "{$roomType->name} - {$occupants} guest(s) x {$nights} night(s)"
            : "{$roomType->name} - {$nights} night(s)";

        $addon = null;
        if ($services->isNotEmpty() && in_array($status, ['approved', 'confirmed', 'checked_in', 'checked_out'], true) && mt_rand(1, 100) <= 28) {
            $addon = $services[mt_rand(0, $services->count() - 1)];
        }

        $addonAmount = $addon ? (float) $addon->price : 0.0;

        $discountDeclared = mt_rand(1, 100) <= 24;
        $discountType = null;
        $discountPercent = 0.0;

        if ($discountDeclared) {
            $discountType = ['student', 'senior_citizen', 'pwd'][mt_rand(0, 2)];
            $discountPercent = match ($discountType) {
                'student' => 10.0,
                'senior_citizen', 'pwd' => 20.0,
                default => 0.0,
            };
        }

        $subtotal = $roomRate + $addonAmount;
        $discountAmount = round($subtotal * ($discountPercent / 100), 2);
        $totalDue = round(max(0, $subtotal - $discountAmount), 2);

        $paymentAmount = match ($status) {
            'checked_out' => $totalDue,
            'checked_in' => mt_rand(1, 100) <= 55 ? $totalDue : round($totalDue * (mt_rand(45, 75) / 100), 2),
            'confirmed' => round($totalDue * ($depositPercentage / 100), 2),
            default => 0.0,
        };

        $balanceDue = round(max(0, $totalDue - $paymentAmount), 2);
        $paymentStatus = $paymentAmount <= 0
            ? 'pending'
            : ($balanceDue <= 0 ? 'paid' : 'partially_paid');

        return [
            'room_rate' => round($roomRate, 2),
            'charge_qty' => $chargeQty,
            'charge_description' => $chargeDescription,
            'addon' => $addon,
            'addon_amount' => round($addonAmount, 2),
            'discount_declared' => $discountDeclared,
            'discount_type' => $discountType,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total_due' => $totalDue,
            'payment_amount' => $paymentAmount,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
            'deposit_percentage' => $depositPercentage,
        ];
    }

    private function buildReservationPayload(
        int $sequence,
        string $status,
        RoomType $roomType,
        array $guestPayloads,
        array $dateWindow,
        array $financials,
        ?User $reviewer,
        ?Room $allocatedRoom,
        float $depositPercentage,
        Carbon $today,
    ): array {
        $primaryGuest = $guestPayloads[0];
        $maleGuests = collect($guestPayloads)->where('gender', 'Male')->count();
        $femaleGuests = count($guestPayloads) - $maleGuests;
        $reviewedAt = $reviewer ? $dateWindow['check_in']->copy()->subDays(mt_rand(1, 6))->startOfDay()->addHours(mt_rand(8, 16)) : null;
        $approvedAt = $reviewer && in_array($status, ['approved', 'confirmed', 'checked_in', 'checked_out'], true)
            ? $reviewedAt
            : null;
        $specialRequests = $this->nameData()['special_requests'][($sequence - 1) % count($this->nameData()['special_requests'])];
        $address = $this->nameData()['addresses'][($sequence - 1) % count($this->nameData()['addresses'])];
        $purpose = $this->nameData()['purposes'][($sequence - 1) % count($this->nameData()['purposes'])];
        $reviewNotes = match ($status) {
            'declined' => 'Declined after review due to unavailability for the requested arrangement.',
            'cancelled' => 'Cancelled at the guest request before arrival.',

            'confirmed' => 'Room held and confirmed for arrival.',
            'checked_in' => 'Guest has arrived and is currently staying.',
            'checked_out' => 'Completed stay.',
            default => null,
        };

        $paymentLinkStatuses = ['approved', 'confirmed', 'checked_in'];

        return [
            'reference_number' => Carbon::today()->year.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'guest_name' => $primaryGuest['full_name'],
            'guest_last_name' => $primaryGuest['last_name'],
            'guest_first_name' => $primaryGuest['first_name'],
            'guest_middle_initial' => $primaryGuest['middle_initial'],
            'guest_email' => strtolower($primaryGuest['first_name']).'.'.strtolower(str_replace(' ', '', $primaryGuest['last_name'])).$sequence.'@example.com',
            'guest_phone' => $primaryGuest['contact_number'],
            'guest_address' => $address,
            'guest_gender' => $primaryGuest['gender'],
            'guest_age' => $primaryGuest['age'],
            'num_male_guests' => $maleGuests,
            'num_female_guests' => $femaleGuests,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $dateWindow['check_in'],
            'check_out_date' => $dateWindow['check_out'],
            'number_of_occupants' => count($guestPayloads),
            'purpose' => $purpose,
            'special_requests' => $sequence % 3 === 0 ? $specialRequests : null,
            'status' => $status,
            'approved_at' => $approvedAt,
            'admin_notes' => $reviewNotes,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => $reviewedAt,
            'addons_total' => $financials['addon_amount'],
            'payments_total' => $financials['payment_amount'],
            'balance_due' => $financials['balance_due'],
            'payment_status' => $financials['payment_status'],
            'payment_link_token' => in_array($status, $paymentLinkStatuses, true) ? (string) Str::uuid() : null,
            'payment_link_expires_at' => in_array($status, $paymentLinkStatuses, true) ? $today->copy()->addHours(48) : null,
            'deposit_percentage' => in_array($status, ['confirmed'], true) ? $depositPercentage : null,
            'discount_declared' => $financials['discount_declared'],
            'discount_declared_type' => $financials['discount_type'],
            'discount_verified' => $financials['discount_declared'] && $reviewer !== null,
            'discount_verification_notes' => $financials['discount_declared'] ? 'Validated during seeded review.' : null,
        ];
    }

    private function seedChargesAndPayments(Reservation $reservation, array $financials, ?User $reviewer): void
    {
        if (! in_array($reservation->status, ['declined', 'cancelled'], true)) {
            ReservationCharge::query()->create([
                'reservation_id' => $reservation->id,
                'charge_type' => 'room_rate',
                'scope_type' => 'reservation',
                'scope_id' => $reservation->id,
                'description' => $financials['charge_description'],
                'qty' => $financials['charge_qty'],
                'unit_price' => $reservation->preferredRoomType->pricing_type === 'per_person'
                    ? $reservation->preferredRoomType->base_rate
                    : $financials['room_rate'] / max(1, $financials['charge_qty']),
                'amount' => $financials['room_rate'],
                'currency' => 'PHP',
                'meta' => null,
                'created_by' => $reviewer?->id,
            ]);

            if ($financials['addon']) {
                ReservationCharge::query()->create([
                    'reservation_id' => $reservation->id,
                    'charge_type' => 'addon',
                    'scope_type' => 'reservation',
                    'scope_id' => $reservation->id,
                    'description' => $financials['addon']->name,
                    'qty' => 1,
                    'unit_price' => $financials['addon']->price,
                    'amount' => $financials['addon_amount'],
                    'currency' => 'PHP',
                    'meta' => ['service_code' => $financials['addon']->code],
                    'created_by' => $reviewer?->id,
                ]);
            }

            if ($financials['discount_amount'] > 0) {
                ReservationCharge::query()->create([
                    'reservation_id' => $reservation->id,
                    'charge_type' => 'discount',
                    'scope_type' => 'reservation',
                    'scope_id' => $reservation->id,
                    'description' => 'Discount: '.str_replace('_', ' ', (string) $financials['discount_type']),
                    'qty' => 1,
                    'unit_price' => -$financials['discount_amount'],
                    'amount' => -$financials['discount_amount'],
                    'currency' => 'PHP',
                    'meta' => [
                        'discount_type' => $financials['discount_type'],
                        'discount_percent' => $financials['discount_percent'],
                    ],
                    'created_by' => $reviewer?->id,
                ]);
            }
        }

        if ($financials['payment_amount'] > 0) {
            ReservationPayment::query()->create([
                'reservation_id' => $reservation->id,
                'amount' => $financials['payment_amount'],
                'payment_mode' => $this->nameData()['payment_modes'][$reservation->id % count($this->nameData()['payment_modes'])],
                'reference_no' => 'PAY-'.Carbon::today()->year.'-'.str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT),
                'or_date' => $reservation->check_in_date->toDateString(),
                'status' => 'posted',
                'received_by' => $reviewer?->id,
                'received_at' => $reservation->check_in_date->copy()->setTime(9, 0),
                'remarks' => $financials['payment_status'] === 'paid' ? 'Full payment received.' : 'Deposit or partial payment received.',
                'is_deposit' => in_array($reservation->status, ['confirmed'], true),
            ]);
        }
    }

    private function seedLogs(Reservation $reservation, string $status, ?User $reviewer, array $payload, ?Room $allocatedRoom): void
    {
        $this->log($reservation, 'reservation_created', "Reservation #{$reservation->reference_number} created.", $reviewer, $reservation->created_at);

        if ($reviewer && in_array($status, ['approved', 'confirmed', 'checked_in', 'checked_out'], true)) {
            $this->log($reservation, 'reservation_approved', "Reservation #{$reservation->reference_number} approved.", $reviewer, $payload['reviewed_at'] ?? $reservation->created_at);
        }

        if ($status === 'confirmed' && $allocatedRoom) {
            $this->log($reservation, 'reservation_confirmed', "Reservation #{$reservation->reference_number} confirmed with Room {$allocatedRoom->room_number}.", $reviewer, $reservation->reviewed_at);
        }

        if ($status === 'declined') {
            $this->log($reservation, 'reservation_declined', "Reservation #{$reservation->reference_number} declined.", $reviewer, $reservation->reviewed_at);
        }

        if ($status === 'cancelled') {
            $this->log($reservation, 'reservation_cancelled', "Reservation #{$reservation->reference_number} cancelled.", $reviewer, $reservation->reviewed_at ?? $reservation->updated_at);
        }

        if ($status === 'checked_out') {
            $this->log($reservation, 'reservation_checked_out', "Reservation #{$reservation->reference_number} checked out.", $reviewer, $reservation->check_out_date->copy()->setTime(11, 0));
        }
    }

    private function seedHold(Reservation $reservation, Room $room, string $status, ?User $reviewer, Carbon $today): void
    {
        RoomHold::query()->create([
            'room_id' => $room->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
            'expires_at' => null,
        ]);
    }

    private function seedAssignmentsAndSnapshot(
        Reservation $reservation,
        RoomType $roomType,
        Collection $guests,
        Room $allocatedRoom,
        ?User $reviewer,
        array $financials,
        ?Service $addon,
        Carbon $today,
    ): void {
        $checkedInAt = $reservation->check_in_date->copy()->setTime(8 + ($reservation->id % 6), ($reservation->id * 7) % 60);
        $checkedOutAt = $reservation->status === 'checked_out'
            ? $reservation->check_out_date->copy()->setTime(9 + ($reservation->id % 3), ($reservation->id * 5) % 60)
            : null;

        $assignGuests = $roomType->room_sharing_type === 'public' ? $guests : collect([$guests->first()]);
        $primaryGuest = $guests->first();
        $paymentMode = $this->nameData()['payment_modes'][$reservation->id % count($this->nameData()['payment_modes'])];
        $referenceNumber = 'OR-'.Carbon::today()->year.'-'.str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT);
        $nationality = $this->nameData()['nationalities'][$reservation->id % count($this->nameData()['nationalities'])];

        foreach ($assignGuests as $guest) {
            RoomAssignment::query()->create([
                'reservation_id' => $reservation->id,
                'guest_id' => $guest->id,
                'room_id' => $allocatedRoom->id,
                'assigned_by' => $reviewer?->id ?? User::query()->value('id'),
                'assigned_at' => $checkedInAt->copy()->subDay(),
                'checked_in_at' => $checkedInAt,
                'checked_in_by' => $reviewer?->id,
                'checked_out_at' => $checkedOutAt,
                'checked_out_by' => $checkedOutAt ? $reviewer?->id : null,
                'status' => $reservation->status === 'checked_out' ? 'checked_out' : 'checked_in',
                'guest_last_name' => $guest->last_name,
                'guest_first_name' => $guest->first_name,
                'guest_middle_initial' => $guest->middle_initial,
                'guest_gender' => $guest->gender,
                'guest_age' => $guest->age,
                'guest_full_address' => $reservation->guest_address,
                'guest_contact_number' => $guest->contact_number ?? $reservation->guest_phone,
                'id_type' => $guest->id_type,
                'id_number' => $guest->id_number,
                'is_student' => $reservation->discount_declared_type === 'student',
                'is_senior_citizen' => $reservation->discount_declared_type === 'senior_citizen',
                'is_pwd' => $reservation->discount_declared_type === 'pwd',
                'purpose_of_stay' => ucfirst((string) $reservation->purpose),
                'nationality' => $nationality,
                'num_male_guests' => $reservation->num_male_guests,
                'num_female_guests' => $reservation->num_female_guests,
                'detailed_checkin_datetime' => $checkedInAt,
                'detailed_checkout_datetime' => $checkedOutAt,
                'additional_requests' => $addon ? [$addon->code] : [],
                'payment_mode' => $paymentMode,
                'payment_amount' => $financials['payment_amount'],
                'payment_or_number' => $referenceNumber,
                'or_date' => $reservation->check_in_date->toDateString(),
                'remarks' => 'Seeded stay record.',
            ]);
        }

        CheckInSnapshot::query()->create([
            'reservation_id' => $reservation->id,
            'guest_id' => $primaryGuest->id,
            'id_type' => $primaryGuest->id_type,
            'id_number' => $primaryGuest->id_number,
            'nationality' => $nationality,
            'purpose_of_stay' => ucfirst((string) $reservation->purpose),
            'detailed_checkin_datetime' => $checkedInAt,
            'detailed_checkout_datetime' => $checkedOutAt,
            'payment_mode' => $paymentMode,
            'payment_amount' => $financials['payment_amount'],
            'payment_or_number' => $referenceNumber,
            'or_date' => $reservation->check_in_date->toDateString(),
            'additional_requests' => $addon ? [$addon->code] : [],
            'remarks' => 'Seeded check-in snapshot.',
            'captured_by' => $reviewer?->id,
            'captured_at' => $checkedInAt,
        ]);

        $this->log($reservation, 'guest_checked_in', "Guest {$primaryGuest->full_name} checked into Room {$allocatedRoom->room_number}.", $reviewer, $checkedInAt);

        if ($checkedOutAt) {
            $this->log($reservation, 'guest_checked_out', "Guest {$primaryGuest->full_name} checked out of Room {$allocatedRoom->room_number}.", $reviewer, $checkedOutAt);
        }
    }

    private function findAvailableRoom(
        RoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
        int $occupants,
        array $roomSchedules,
    ): ?Room {
        $rooms = $roomType->rooms
            ->filter(fn (Room $room) => $room->capacity >= $occupants)
            ->shuffle();

        foreach ($rooms as $room) {
            $conflict = collect($roomSchedules[$room->id] ?? [])
                ->contains(function (array $slot) use ($checkIn, $checkOut): bool {
                    return $checkIn->lt($slot['check_out']) && $slot['check_in']->lt($checkOut);
                });

            if (! $conflict) {
                return $room;
            }
        }

        return null;
    }

    private function rememberRoomUsage(array &$roomSchedules, Room $room, Carbon $checkIn, Carbon $checkOut): void
    {
        $roomSchedules[$room->id][] = [
            'check_in' => $checkIn->copy(),
            'check_out' => $checkOut->copy(),
        ];
    }

    private function log(Reservation $reservation, string $event, string $description, ?User $actor, ?Carbon $loggedAt): void
    {
        ReservationLog::query()->create([
            'reservation_id' => $reservation->id,
            'event' => $event,
            'description' => $description,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'meta' => null,
            'logged_at' => $loggedAt ?? now(),
        ]);
    }
}
