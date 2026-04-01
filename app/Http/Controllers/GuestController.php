<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use Illuminate\Http\Request;

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

        return view('guest.home', compact('roomTypes'));
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

        // Load rooms with slot availability
        $rooms = $roomType->rooms()
            ->withCount(['roomAssignments as checked_in_count' => fn ($q) => $q->where('status', 'checked_in')])
            ->orderBy('room_number')
            ->get()
            ->map(function ($room) {
                $totalBeds = $room->capacity ?? 0;
                $availableBeds = max(0, $totalBeds - ($room->checked_in_count ?? 0));

                return (object) [
                    'room' => $room,
                    'total_beds' => $totalBeds,
                    'available_beds' => $availableBeds,
                    'occupancy_rate' => $totalBeds > 0 ? round((($totalBeds - $availableBeds) / $totalBeds) * 100) : 0,
                ];
            });

        return view('guest.room-detail', compact('roomType', 'rooms'));
    }

    /**
     * Virtual tours page - list all room types with virtual tours
     */
    public function virtualTours()
    {
        $roomTypes = RoomType::where('is_active', true)
            ->whereNotNull('virtual_tour_url')
            ->where('virtual_tour_url', '!=', '')
            ->select(['id', 'name', 'description', 'virtual_tour_url', 'images'])
            ->get();

        return view('guest.virtual-tours', compact('roomTypes'));
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
        ]);

        // Combine name fields for guest_name (backward compatibility)
        $validated['guest_name'] = trim(
            $validated['guest_first_name'].' '.
            ($validated['guest_middle_initial'] ?? '').' '.
            $validated['guest_last_name']
        );

        $validated['status'] = 'pending';

        $reservation = Reservation::create($validated);

        return redirect()->route('guest.track')
            ->with('success', 'Your reservation has been submitted successfully!')
            ->with('reference_number', $reservation->reference_number);
    }

    /**
     * Track reservation status
     */
    public function track(Request $request)
    {
        $reservation = null;
        $expired = false;
        $reference = $request->get('reference') ?? session('reference_number');

        // Expiry windows (in days) for terminal statuses.
        $expiryDays = [
            'checked_out' => 30,
            'declined' => 14,
            'cancelled' => 14,
        ];

        if ($reference) {
            $reservation = Reservation::where('reference_number', $reference)
                ->with(['preferredRoomType', 'roomAssignments.room', 'roomAssignments.room.roomType'])
                ->first();

            if ($reservation) {
                // Check whether the tracking window has expired for terminal statuses.
                if (isset($expiryDays[$reservation->status])) {
                    $daysSince = $reservation->updated_at->diffInDays(now());
                    if ($daysSince >= $expiryDays[$reservation->status]) {
                        $expired = true;
                        $reservation = null;
                    }
                }

                // Safety net: if reservation is checked out, close any lingering open assignments.
                if ($reservation && $reservation->status === 'checked_out') {
                    RoomAssignment::where('reservation_id', $reservation->id)
                        ->whereNull('checked_out_at')
                        ->update([
                            'status' => 'checked_out',
                            'checked_out_at' => now(),
                        ]);

                    $reservation->load(['preferredRoomType', 'roomAssignments.room', 'roomAssignments.room.roomType']);
                }
            }
        }

        return view('guest.track', compact('reservation', 'reference', 'expired'));
    }
}
