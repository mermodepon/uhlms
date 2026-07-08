<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Reservation;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\TourWaypoint;
use App\Services\RoomHoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\ReservationRoomRequests;

class GuestController extends Controller
{
    /**
     * Home page - landing page for the lodging system
     */
    public function home()
    {
        $roomTypes = RoomType::where('is_active', true)
            ->with('amenities')
            ->get();

        $roomHoldService = app(RoomHoldService::class);

        $roomTypes->each(function (RoomType $roomType) use ($roomHoldService) {
            $this->applyAvailabilitySummary($roomType, $roomHoldService->getCurrentAvailabilitySummary($roomType));
            $roomType->is_date_filtered = false;
        });

        $stayInclusions = Amenity::query()
            ->where('is_active', true)
            ->whereHas('roomTypes', fn ($query) => $query->where('room_types.is_active', true))
            ->withCount([
                'roomTypes as active_room_types_count' => fn ($query) => $query->where('room_types.is_active', true),
            ])
            ->orderByDesc('active_room_types_count')
            ->orderBy('name')
            ->limit(4)
            ->get();

        $optionalAddOns = Service::query()
            ->active()
            ->ordered()
            ->limit(4)
            ->get();

        return view('guest.home', compact('roomTypes', 'stayInclusions', 'optionalAddOns'));
    }

    /**
     * Room catalog - browse all room types
     */
    public function rooms(Request $request)
    {
        $checkIn = $request->check_in ? Carbon::parse($request->check_in) : null;
        $checkOut = $request->check_out ? Carbon::parse($request->check_out) : null;
        $guests = $request->guests ? (int) $request->guests : null;
        $showUnavailable = $request->boolean('show_unavailable', true);

        $roomTypes = RoomType::where('is_active', true)
            ->with('amenities')
            ->get();

        $roomHoldService = app(RoomHoldService::class);

        // Calculate availability based on whether dates are provided
        if ($checkIn && $checkOut) {
            foreach ($roomTypes as $roomType) {
                $this->applyAvailabilitySummary(
                    $roomType,
                    $roomHoldService->getDateAvailabilitySummary($roomType, $checkIn, $checkOut, $guests)
                );
                $roomType->is_date_filtered = true;
            }
        } else {
            foreach ($roomTypes as $roomType) {
                $this->applyAvailabilitySummary($roomType, $roomHoldService->getCurrentAvailabilitySummary($roomType, $guests));
                $roomType->is_date_filtered = false;
            }
        }

        $roomTypes = $roomTypes
            ->sortBy([
                fn (RoomType $roomType): int => ($roomType->can_accommodate_requested_guests ?? false) ? 0 : 1,
                fn (RoomType $roomType): string => Str::lower($roomType->name),
                fn (RoomType $roomType): int => $roomType->id,
            ])
            ->values();

        $availableRoomTypesCount = $roomTypes
            ->filter(fn (RoomType $roomType): bool => (bool) ($roomType->can_accommodate_requested_guests ?? false))
            ->count();
        $unavailableRoomTypesCount = $roomTypes->count() - $availableRoomTypesCount;

        if (! $showUnavailable) {
            $roomTypes = $roomTypes
                ->filter(fn (RoomType $roomType): bool => (bool) ($roomType->can_accommodate_requested_guests ?? false))
                ->values();
        }

        return view('guest.rooms', compact(
            'roomTypes',
            'checkIn',
            'checkOut',
            'guests',
            'showUnavailable',
            'availableRoomTypesCount',
            'unavailableRoomTypesCount'
        ));
    }

    /**
     * Room type details with virtual tour
     */
    public function roomDetail(Request $request, RoomType $roomType)
    {
        $roomType->load('amenities');

        $checkIn = null;
        $checkOut = null;
        $guests = $request->filled('guests') ? max(1, (int) $request->guests) : null;
        $roomHoldService = app(RoomHoldService::class);

        try {
            $checkIn = $request->filled('check_in') ? Carbon::parse($request->check_in) : null;
            $checkOut = $request->filled('check_out') ? Carbon::parse($request->check_out) : null;
        } catch (\Throwable) {
            $checkIn = null;
            $checkOut = null;
        }

        if ($checkIn && $checkOut && $checkOut->gt($checkIn)) {
            $this->applyAvailabilitySummary(
                $roomType,
                $roomHoldService->getDateAvailabilitySummary($roomType, $checkIn, $checkOut, $guests)
            );
            $roomType->is_date_filtered = true;
        } else {
            $this->applyAvailabilitySummary($roomType, $roomHoldService->getCurrentAvailabilitySummary($roomType, $guests));
            $roomType->is_date_filtered = false;
        }

        $tourWaypointSlug = TourWaypoint::query()
            ->active()
            ->where('linked_room_type_id', $roomType->id)
            ->orderByRaw("
                CASE
                    WHEN type = 'room-interior' THEN 0
                    WHEN type = 'room-door' THEN 1
                    ELSE 2
                END
            ")
            ->ordered()
            ->value('slug');

        return view('guest.room-detail', compact('roomType', 'tourWaypointSlug', 'checkIn', 'checkOut', 'guests'));
    }

    /**
     * Virtual tours page - redirects to the PSV interactive tour viewer
     */
    public function virtualTours()
    {
        return redirect()->route('guest.tour.viewer');
    }

    /**
     * Reservation form
     */
    public function reserveForm()
    {
        $roomTypes = RoomType::where('is_active', true)
            ->with('rooms')
            ->get()
            ->each(function (RoomType $roomType) {
                $this->applyAvailabilitySummary($roomType, app(RoomHoldService::class)->getCurrentAvailabilitySummary($roomType));
            });

        return view('guest.reserve', compact('roomTypes'));
    }

    /**
     * Submit reservation
     */
    public function reserveSubmit(Request $request)
    {
        $request->merge([
            'guest_last_name' => trim((string) $request->input('guest_last_name', '')),
            'guest_first_name' => trim((string) $request->input('guest_first_name', '')),
            'guest_middle_initial' => trim((string) $request->input('guest_middle_initial', '')) ?: null,
            'guest_phone' => trim((string) $request->input('guest_phone', '')) ?: null,
        ]);

        $validated = $request->validate([
            'guest_last_name' => 'required|string|max:255',
            'guest_first_name' => 'required|string|max:255',
            'guest_middle_initial' => 'nullable|string|max:10',
            'guest_gender' => 'required|in:Male,Female,Other',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => ['required', 'string', 'max:20', 'regex:/^(09\d{9}|\+639\d{9}|639\d{9})$/'],
            'guest_age' => 'required|integer|min:18|max:120',
            'guest_address' => 'nullable|string|max:1000',
            'preferred_room_type_id' => 'required|exists:room_types,id',
            'requested_room_count' => 'nullable|integer|min:1|max:20',
            'room_requests' => 'nullable|array|max:7',
            'room_requests.*.room_type_id' => 'nullable|exists:room_types,id',
            'room_requests.*.requested_room_count' => 'nullable|integer|min:1|max:20',
            'room_requests.*.occupant_count' => 'nullable|integer|min:1|max:200',
            'room_requests.*.notes' => 'nullable|string|max:500',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'number_of_occupants' => 'required|integer|min:1',
            'purpose' => 'nullable|string|max:100',
            'special_requests' => 'nullable|string|max:2000',
            'discount_declared' => 'nullable|boolean',
            'discount_declared_type' => 'required_if:discount_declared,1|nullable|in:senior_citizen,pwd,student',
            'availability_acknowledged' => 'nullable|boolean',
        ]);

        // Combine name fields for guest_name (backward compatibility)
        $validated['guest_name'] = trim(
            $validated['guest_first_name'].' '.
            ($validated['guest_middle_initial'] ?? '').' '.
            $validated['guest_last_name']
        );

        $roomRequestLines = ReservationRoomRequests::fromRequest($request);
        $requestValidation = ReservationRoomRequests::validateAvailability(
            $roomRequestLines,
            $validated['check_in_date'],
            $validated['check_out_date']
        );

        if (! empty($requestValidation['errors'])) {
            throw ValidationException::withMessages($requestValidation['errors']);
        }

        $validated['status'] = 'pending';
        $validated['discount_declared'] = $request->has('discount_declared');

        if (! empty($requestValidation['warnings'])) {
            $warning = implode(' ', $requestValidation['warnings'])
                .' You can still submit this request for staff review by checking the acknowledgement below.';

            if (! $request->boolean('availability_acknowledged')) {
                throw ValidationException::withMessages([
                    'availability_acknowledged' => $warning,
                ]);
            }

            $validated['special_requests'] = trim(
                (($validated['special_requests'] ?? null) ? rtrim($validated['special_requests'])."\n" : '')
                .'[Availability warning acknowledged by guest: '
                .implode(' ', $requestValidation['warnings'])
                .' Requested: '.$requestValidation['summary'].' on '
                .$validated['check_in_date'].' to '.$validated['check_out_date'].']'
            );
        }

        unset($validated['availability_acknowledged']);
        unset($validated['requested_room_count'], $validated['room_requests']);

        $validated = ReservationRoomRequests::applyToReservationData($validated, $roomRequestLines);

        $reservation = Reservation::create($validated);
        ReservationRoomRequests::persist($reservation, $roomRequestLines);

        return redirect()->route('guest.track')
            ->with('success', 'Your reservation request has been submitted successfully!')
            ->with('reference_number', $reservation->reference_number)
            ->with('guest_email', $reservation->guest_email);
    }

    /**
     * Track reservation status
     */
    public function track(Request $request)
    {
        $reservation = null;
        $expired = false;
        $reference = $request->get('reference') ?? session('reference_number');
        $guestEmail = $request->get('guest_email') ?? session('guest_email');

        if ($request->filled('reference') || $request->filled('guest_email')) {
            $request->validate([
                'reference' => ['required', 'string', 'max:255'],
                'guest_email' => ['required', 'email', 'max:255'],
            ]);
        }

        if ($reference && $guestEmail) {
            $reservation = Reservation::where('reference_number', $reference)
                ->whereRaw('LOWER(guest_email) = ?', [Str::lower($guestEmail)])
                ->first();

            [$reservation, $expired] = $this->resolveTrackedReservation($reservation);
        }

        return view('guest.track', compact('reservation', 'reference', 'guestEmail', 'expired'));
    }

    public function trackSecure(Request $request, Reservation $reservation)
    {
        $reference = $reservation->reference_number;
        $guestEmail = $reservation->guest_email;

        [$reservation, $expired] = $this->resolveTrackedReservation($reservation);

        return view('guest.track', [
            'reservation' => $reservation,
            'reference' => $reference,
            'guestEmail' => $guestEmail,
            'expired' => $expired,
        ]);
    }

    private function resolveTrackedReservation(?Reservation $reservation): array
    {
        if (! $reservation) {
            return [null, false];
        }

        // Expiry windows (in days) for terminal statuses.
        $expiryDays = [
            'checked_out' => 30,
            'declined' => 14,
            'cancelled' => 14,
        ];

        if (isset($expiryDays[$reservation->status])) {
            $daysSince = $reservation->updated_at->diffInDays(now());
            if ($daysSince >= $expiryDays[$reservation->status]) {
                return [null, true];
            }
        }

        // Safety net: if reservation is checked out, close any lingering open assignments.
        if ($reservation->status === 'checked_out') {
            RoomAssignment::where('reservation_id', $reservation->id)
                ->whereNull('checked_out_at')
                ->update([
                    'status' => 'checked_out',
                    'checked_out_at' => now(),
                ]);
        }

        $reservation->load([
            'preferredRoomType',
            'roomRequests.roomType',
            'roomAssignments.room',
            'roomAssignments.room.roomType',
            'payments' => fn ($q) => $q->where('gateway', 'paymongo')->latest(),
        ]);

        return [$reservation, false];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function applyAvailabilitySummary(RoomType $roomType, array $summary): void
    {
        $roomType->available_rooms_count = $summary['available_rooms_count'] ?? 0;
        $roomType->available_beds_count = $summary['available_beds_count'] ?? null;
        $roomType->total_rooms_count = $summary['total_rooms_count'] ?? 0;
        $roomType->total_beds_count = $summary['total_beds_count'] ?? null;
        $roomType->availability_display_count = $summary['availability_display_count'] ?? 0;
        $roomType->availability_display_unit = $summary['availability_display_unit'] ?? 'rooms';
        $roomType->availability_label = $summary['availability_label'] ?? '0 rooms available';
        $roomType->can_accommodate_requested_guests = $summary['can_accommodate_requested_guests'] ?? false;
    }

    /**
     * @return array{room_type: RoomType, summary: array<string,mixed>}|null
     */
    private function resolveAvailabilityContext(int $roomTypeId, string $checkInDate, string $checkOutDate, int $occupants): ?array
    {
        $roomType = RoomType::query()
            ->where('is_active', true)
            ->find($roomTypeId);

        if (! $roomType) {
            return null;
        }

        $summary = app(RoomHoldService::class)->getDateAvailabilitySummary(
            $roomType,
            Carbon::parse($checkInDate),
            Carbon::parse($checkOutDate),
            $occupants
        );

        return ($summary['can_accommodate_requested_guests'] ?? false)
            ? null
            : ['room_type' => $roomType, 'summary' => $summary];
    }

    /**
     * @return array{max:int,message:string}|null
     */
    private function resolveOccupantLimit(int $roomTypeId, string $checkInDate, string $checkOutDate): ?array
    {
        $roomType = RoomType::query()
            ->where('is_active', true)
            ->find($roomTypeId);

        if (! $roomType) {
            return null;
        }

        if ($roomType->isPrivate()) {
            $capacity = max(1, (int) ($roomType->capacity ?? 1));

            return [
                'max' => $capacity,
                'message' => "This room type allows up to {$capacity} occupants.",
            ];
        }

        $summary = app(RoomHoldService::class)->getDateAvailabilitySummary(
            $roomType,
            Carbon::parse($checkInDate),
            Carbon::parse($checkOutDate),
            null
        );

        $availableBeds = max(0, (int) ($summary['available_beds_count'] ?? 0));

        return [
            'max' => $availableBeds,
            'message' => $availableBeds > 0
                ? "Only {$availableBeds} beds are available for these dates."
                : 'No beds are available for these dates.',
        ];
    }
}
