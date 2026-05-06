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

        return view('guest.rooms', compact('roomTypes', 'checkIn', 'checkOut', 'guests'));
    }

    /**
     * Room type details with virtual tour
     */
    public function roomDetail(RoomType $roomType)
    {
        $roomType->load('amenities');
        $this->applyAvailabilitySummary($roomType, app(RoomHoldService::class)->getCurrentAvailabilitySummary($roomType));

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

        return view('guest.room-detail', compact('roomType', 'tourWaypointSlug'));
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
        $validated = $request->validate([
            'guest_last_name' => 'required|string|max:255',
            'guest_first_name' => 'required|string|max:255',
            'guest_middle_initial' => 'nullable|string|max:10',
            'guest_gender' => 'required|in:Male,Female,Other',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'guest_age' => 'nullable|integer|min:1|max:120',
            'guest_address' => 'nullable|string|max:1000',
            'preferred_room_type_id' => 'required|exists:room_types,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'number_of_occupants' => 'required|integer|min:1|max:20',
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

        $validated['status'] = 'pending';
        $validated['discount_declared'] = $request->has('discount_declared');

        $availabilityContext = $this->resolveAvailabilityContext(
            (int) $validated['preferred_room_type_id'],
            $validated['check_in_date'],
            $validated['check_out_date'],
            (int) $validated['number_of_occupants']
        );

        if ($availabilityContext !== null) {
            $warning = 'This room type currently shows '
                .$availabilityContext['summary']['availability_label']
                .' for your selected dates and guest count. You can still submit this request for staff review by checking the acknowledgement below.';

            if (! $request->boolean('availability_acknowledged')) {
                throw ValidationException::withMessages([
                    'availability_acknowledged' => $warning,
                ]);
            }

            $validated['special_requests'] = trim(
                (($validated['special_requests'] ?? null) ? rtrim($validated['special_requests'])."\n" : '')
                .'[Availability warning acknowledged by guest: '
                .$availabilityContext['summary']['availability_label']
                .' for '.$validated['number_of_occupants'].' occupant(s) on '
                .$validated['check_in_date'].' to '.$validated['check_out_date'].']'
            );
        }

        unset($validated['availability_acknowledged']);

        $reservation = Reservation::create($validated);

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
}
