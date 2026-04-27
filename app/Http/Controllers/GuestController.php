<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Reservation;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\TourWaypoint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    /**
     * Home page - landing page for the lodging system
     */
    public function home()
    {
        $roomTypes = RoomType::where('is_active', true)
            ->withCount('availableRooms')
            ->with('amenities')
            ->get();

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

        // Match the preview to the default scene used when the tour starts.
        $previewWaypoint = TourWaypoint::query()
            ->where('is_active', true)
            ->where('type', 'entrance')
            ->orderBy('position_order')
            ->first()
            ?? TourWaypoint::query()
                ->where('is_active', true)
                ->orderBy('position_order')
                ->first();

        return view('guest.home', compact('roomTypes', 'previewWaypoint', 'stayInclusions', 'optionalAddOns'));
    }

    /**
     * Room catalog - browse all room types
     */
    public function rooms()
    {
        $roomTypes = RoomType::where('is_active', true)
            ->withCount(['rooms', 'availableRooms'])
            ->with('amenities')
            ->get();

        return view('guest.rooms', compact('roomTypes'));
    }

    /**
     * Room type details with virtual tour
     */
    public function roomDetail(RoomType $roomType)
    {
        $roomType->load('amenities');
        $roomType->loadCount(['rooms', 'availableRooms']);

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

        // Calculate aggregate availability only (no individual room details for security)
        $isPrivate = $roomType->isPrivate();
        
        if ($isPrivate) {
            // For private rooms, just use the count relationships
            $totalCount = $roomType->rooms_count;
            $availableCount = $roomType->available_rooms_count;
        } else {
            // For shared rooms, calculate total beds
            $totalBeds = $roomType->rooms()->sum('capacity');
            $availableBeds = $roomType->rooms()->get()->sum(function ($room) {
                $checkedIn = $room->roomAssignments()->where('status', 'checked_in')->count();
                return max(0, ($room->capacity ?? 0) - $checkedIn);
            });
        }

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
            ->withCount('availableRooms')
            ->get()
            ->each(function ($roomType) {
                // Calculate slot availability based on capacity vs active assignments
                $roomType->total_beds = $roomType->rooms->sum('capacity');
                $roomType->available_beds = $roomType->rooms->sum(fn ($r) => max(0, ($r->capacity ?? 0) - $r->roomAssignments()->where('status', 'checked_in')->count()));
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
        ]);

        // Combine name fields for guest_name (backward compatibility)
        $validated['guest_name'] = trim(
            $validated['guest_first_name'].' '.
            ($validated['guest_middle_initial'] ?? '').' '.
            $validated['guest_last_name']
        );

        $validated['status'] = 'pending';
        $validated['discount_declared'] = $request->has('discount_declared');

        $reservation = Reservation::create($validated);

        return redirect()->route('guest.track')
            ->with('success', 'Your reservation has been submitted successfully!')
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
}
