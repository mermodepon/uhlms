<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Reservation;
use App\Models\ReservationFeedback;
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
            ->with([
                'amenities',
                'rooms:id,room_type_id,capacity,status,is_active',
            ])
            ->get();

        $roomHoldService = app(RoomHoldService::class);

        $roomTypes = $this->expandCapacityVariants($roomTypes, $roomHoldService);

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

        $testimonials = ReservationFeedback::query()
            ->publicTestimonials()
            ->with('reservation.roomAssignments.room.roomType:id,name')
            ->latest('reviewed_at')
            ->limit(6)
            ->get();

        return view('guest.home', compact('roomTypes', 'stayInclusions', 'optionalAddOns', 'testimonials'));
    }

    /**
     * About page - configurable guest-facing property information.
     */
    public function about()
    {
        return view('guest.about');
    }

    /**
     * Room catalog - browse all room types
     */
    public function rooms(Request $request)
    {
        $validated = $request->validate([
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:200'],
            'show_unavailable' => ['nullable', 'boolean'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer'],
            'room_sharing_type' => ['nullable', 'in:private,public'],
            'pricing_type' => ['nullable', 'in:flat_rate,per_person'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:recommended,price_low,price_high,capacity,name'],
        ]);

        $checkIn = filled($validated['check_in'] ?? null) ? Carbon::parse($validated['check_in']) : null;
        $checkOut = filled($validated['check_out'] ?? null) ? Carbon::parse($validated['check_out']) : null;
        $guests = filled($validated['guests'] ?? null) ? (int) $validated['guests'] : null;
        $showUnavailable = $request->boolean('show_unavailable', true);
        $roomSharingType = $validated['room_sharing_type'] ?? null;
        $pricingType = $validated['pricing_type'] ?? null;
        $priceMin = filled($validated['price_min'] ?? null) ? (float) $validated['price_min'] : null;
        $priceMax = filled($validated['price_max'] ?? null) ? (float) $validated['price_max'] : null;
        $sort = $validated['sort'] ?? 'recommended';

        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            [$priceMin, $priceMax] = [$priceMax, $priceMin];
        }

        $activeAmenities = Amenity::query()
            ->where('is_active', true)
            ->whereHas('roomTypes', fn ($query) => $query->where('room_types.is_active', true))
            ->orderBy('name')
            ->get(['id', 'name']);

        $activeAmenityIds = $activeAmenities->pluck('id')->all();
        $selectedAmenityIds = collect($validated['amenities'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->intersect($activeAmenityIds)
            ->unique()
            ->values()
            ->all();

        $roomTypesQuery = RoomType::query()
            ->where('is_active', true)
            ->with(['amenities' => fn ($query) => $query->where('amenities.is_active', true)]);

        foreach ($selectedAmenityIds as $amenityId) {
            $roomTypesQuery->whereHas(
                'amenities',
                fn ($query) => $query->where('amenities.id', $amenityId)->where('amenities.is_active', true)
            );
        }

        if ($roomSharingType) {
            $roomTypesQuery->where('room_sharing_type', $roomSharingType);
        }

        if ($pricingType) {
            $roomTypesQuery->where('pricing_type', $pricingType);
        }

        if ($priceMin !== null) {
            $roomTypesQuery->where('base_rate', '>=', $priceMin);
        }

        if ($priceMax !== null) {
            $roomTypesQuery->where('base_rate', '<=', $priceMax);
        }

        $roomTypes = $roomTypesQuery->get();

        $roomHoldService = app(RoomHoldService::class);

        $roomTypes = $this->expandCapacityVariants($roomTypes, $roomHoldService, $checkIn, $checkOut, $guests);

        $roomTypes = match ($sort) {
            'price_low' => $roomTypes->sortBy([
                fn (RoomType $roomType): float => (float) $roomType->base_rate,
                fn (RoomType $roomType): string => Str::lower($roomType->name),
            ]),
            'price_high' => $roomTypes->sortByDesc('base_rate'),
            'capacity' => $roomTypes->sortByDesc(fn (RoomType $roomType): int => (int) ($roomType->variant_capacity ?? $roomType->capacity)),
            'name' => $roomTypes->sortBy([
                fn (RoomType $roomType): string => Str::lower($roomType->name),
                fn (RoomType $roomType): int => $roomType->id,
            ]),
            default => $roomTypes->sortBy([
                fn (RoomType $roomType): int => ($roomType->can_accommodate_requested_guests ?? false) ? 0 : 1,
                fn (RoomType $roomType): string => Str::lower($roomType->name),
                fn (RoomType $roomType): int => $roomType->id,
            ]),
        };

        $roomTypes = $roomTypes->values();

        $availableRoomTypesCount = $roomTypes
            ->filter(fn (RoomType $roomType): bool => (bool) ($roomType->can_accommodate_requested_guests ?? false))
            ->count();
        $unavailableRoomTypesCount = $roomTypes->count() - $availableRoomTypesCount;

        if (! $showUnavailable) {
            $roomTypes = $roomTypes
                ->filter(fn (RoomType $roomType): bool => (bool) ($roomType->can_accommodate_requested_guests ?? false))
                ->values();
        }

        $filterQuery = collect([
            'check_in' => $checkIn?->format('Y-m-d'),
            'check_out' => $checkOut?->format('Y-m-d'),
            'guests' => $guests,
            'amenities' => $selectedAmenityIds,
            'room_sharing_type' => $roomSharingType,
            'pricing_type' => $pricingType,
            'price_min' => $priceMin !== null ? (int) $priceMin : null,
            'price_max' => $priceMax !== null ? (int) $priceMax : null,
            'sort' => $sort !== 'recommended' ? $sort : null,
        ])->filter(function ($value) {
            if (is_array($value)) {
                return ! empty($value);
            }

            return filled($value);
        })->all();

        $activeFilterLabels = collect();

        foreach ($activeAmenities->whereIn('id', $selectedAmenityIds) as $amenity) {
            $activeFilterLabels->push($amenity->name);
        }

        if ($roomSharingType) {
            $activeFilterLabels->push($roomSharingType === 'private' ? 'Private rooms' : 'Shared / dormitory');
        }

        if ($pricingType) {
            $activeFilterLabels->push($pricingType === 'flat_rate' ? 'Per room/night' : 'Per person/night');
        }

        if ($priceMin !== null || $priceMax !== null) {
            $activeFilterLabels->push(match (true) {
                $priceMin !== null && $priceMax !== null => 'PHP '.number_format($priceMin, 0).' - PHP '.number_format($priceMax, 0),
                $priceMin !== null => 'PHP '.number_format($priceMin, 0).'+',
                default => 'Up to PHP '.number_format($priceMax, 0),
            });
        }

        if ($sort !== 'recommended') {
            $activeFilterLabels->push(match ($sort) {
                'price_low' => 'Lowest price first',
                'price_high' => 'Highest price first',
                'capacity' => 'Largest capacity first',
                'name' => 'Name A-Z',
                default => null,
            });
        }

        $activeFilterLabels = $activeFilterLabels->filter()->values();
        $hasAdvancedFilters = $activeFilterLabels->isNotEmpty();

        return view('guest.rooms', compact(
            'roomTypes',
            'checkIn',
            'checkOut',
            'guests',
            'showUnavailable',
            'availableRoomTypesCount',
            'unavailableRoomTypesCount',
            'activeAmenities',
            'selectedAmenityIds',
            'roomSharingType',
            'pricingType',
            'priceMin',
            'priceMax',
            'sort',
            'filterQuery',
            'activeFilterLabels',
            'hasAdvancedFilters'
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
        $capacity = $request->filled('capacity') ? max(1, (int) $request->capacity) : null;
        $roomHoldService = app(RoomHoldService::class);

        try {
            $checkIn = $request->filled('check_in') ? Carbon::parse($request->check_in) : null;
            $checkOut = $request->filled('check_out') ? Carbon::parse($request->check_out) : null;
        } catch (\Throwable) {
            $checkIn = null;
            $checkOut = null;
        }

        if ($capacity !== null && ! app(RoomHoldService::class)->getSellableCapacities($roomType)->contains($capacity)) {
            abort(404);
        }

        if ($checkIn && $checkOut && $checkOut->gt($checkIn)) {
            $this->applyAvailabilitySummary(
                $roomType,
                $roomHoldService->getDateAvailabilitySummary($roomType, $checkIn, $checkOut, $guests, $capacity)
            );
            $roomType->is_date_filtered = true;
        } else {
            $this->applyAvailabilitySummary($roomType, $roomHoldService->getCurrentAvailabilitySummary($roomType, $guests, $capacity));
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

        $roomType->variant_capacity = $capacity;
        $roomType->has_capacity_variants = app(RoomHoldService::class)->getSellableCapacities($roomType)->count() > 1;

        return view('guest.room-detail', compact('roomType', 'tourWaypointSlug', 'checkIn', 'checkOut', 'guests', 'capacity'));
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
        $guestAccount = auth('guest')->user();
        $roomTypes = RoomType::where('is_active', true)
            ->with('rooms')
            ->get()
            ;

        $roomTypes = $this->expandCapacityVariants($roomTypes, app(RoomHoldService::class));

        return view('guest.reserve', compact('roomTypes', 'guestAccount'));
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
            'preferred_room_capacity' => 'nullable|integer|min:1|max:100',
            'requested_room_count' => 'nullable|integer|min:1|max:20',
            'room_requests' => 'nullable|array|max:7',
            'room_requests.*.room_type_id' => 'nullable|exists:room_types,id',
            'room_requests.*.requested_capacity' => 'nullable|integer|min:1|max:100',
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
        $guestAccount = auth('guest')->user();

        if ($guestAccount && Str::lower($guestAccount->email) === Str::lower($validated['guest_email'])) {
            $validated['guest_account_id'] = $guestAccount->id;
        }

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
        unset($validated['requested_room_count'], $validated['preferred_room_capacity'], $validated['room_requests']);

        $validated = ReservationRoomRequests::applyToReservationData($validated, $roomRequestLines);

        $reservation = Reservation::create($validated);
        ReservationRoomRequests::persist($reservation, $roomRequestLines);

        return redirect()->route('guest.track')
            ->with('success', 'Your reservation request has been submitted successfully!')
            ->with('reference_number', $reservation->reference_number)
            ->with('guest_email', $reservation->guest_email)
            ->with('guest_account_prompt', ! $guestAccount);
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
     * Turn room types with mixed room capacities into guest-facing capacity options.
     *
     * @param  \Illuminate\Support\Collection<int, RoomType>  $roomTypes
     * @return \Illuminate\Support\Collection<int, RoomType>
     */
    private function expandCapacityVariants(
        \Illuminate\Support\Collection $roomTypes,
        RoomHoldService $roomHoldService,
        ?Carbon $checkIn = null,
        ?Carbon $checkOut = null,
        ?int $guests = null,
    ): \Illuminate\Support\Collection {
        return $roomTypes
            ->flatMap(function (RoomType $roomType) use ($roomHoldService, $checkIn, $checkOut, $guests) {
                $capacities = $roomHoldService->getSellableCapacities($roomType);
                if ($capacities->isEmpty()) {
                    $capacities = collect([max(1, (int) $roomType->capacity)]);
                }
                $hasVariants = $capacities->count() > 1;
                $displayCapacities = $guests === null
                    ? $capacities
                    : (($matching = $capacities->filter(fn (int $capacity): bool => $capacity >= $guests))->isNotEmpty() ? $matching : $capacities);

                return $displayCapacities
                    ->map(function (int $capacity) use ($roomType, $roomHoldService, $checkIn, $checkOut, $guests, $hasVariants): RoomType {
                        $variant = clone $roomType;
                        $summary = $checkIn && $checkOut && $checkOut->gt($checkIn)
                            ? $roomHoldService->getDateAvailabilitySummary($variant, $checkIn, $checkOut, $guests, $capacity)
                            : $roomHoldService->getCurrentAvailabilitySummary($variant, $guests, $capacity);

                        $this->applyAvailabilitySummary($variant, $summary);
                        $variant->variant_capacity = $capacity;
                        $variant->has_capacity_variants = $hasVariants;
                        $variant->is_date_filtered = $checkIn !== null && $checkOut !== null;

                        return $variant;
                    });
            })
            ->values();
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
