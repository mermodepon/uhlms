<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use App\Models\Reservation;
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

        return view('guest.room-detail', compact('roomType'));
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
            ->withCount('availableRooms')
            ->get();

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
            'guest_address' => 'nullable|string|max:1000',
            'guest_organization' => 'nullable|string|max:255',
            'preferred_room_type_id' => 'required|exists:room_types,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'number_of_occupants' => 'required|integer|min:1|max:20',
            'purpose' => 'nullable|string|max:100',
            'special_requests' => 'nullable|string|max:2000',
        ]);

        // Combine name fields for guest_name (backward compatibility)
        $validated['guest_name'] = trim(
            $validated['guest_first_name'] . ' ' . 
            ($validated['guest_middle_initial'] ?? '') . ' ' . 
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
        $reference = $request->get('reference') ?? session('reference_number');

        if ($reference) {
            $reservation = Reservation::where('reference_number', $reference)
                ->with(['preferredRoomType', 'roomAssignments.room', 'stayLogs.room'])
                ->first();
        }

        return view('guest.track', compact('reservation', 'reference'));
    }
}
