<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\RoomHold;
use App\Models\TourWaypoint;
use App\Models\RoomType;
use App\Services\RoomHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class TourController extends Controller
{
    /**
     * Tour viewer page
     */
    public function viewer(?string $slug = null)
    {
        $startWaypoint = $slug ?: 'entrance';
        
        return view('guest.virtual-tour-viewer', compact('startWaypoint'));
    }

    /**
     * Get all active waypoints ordered by position
     */
    public function waypoints(): JsonResponse
    {
        $waypoints = TourWaypoint::with('activeHotspots')
            ->active()
            ->ordered()
            ->get()
            ->map(function ($waypoint) {
                return [
                    'id' => $waypoint->id,
                    'name' => $waypoint->name,
                    'slug' => $waypoint->slug,
                    'type' => $waypoint->type,
                    'type_label' => $waypoint->getTypeLabel(),
                    'panorama_image' => $waypoint->getPanoramaUrl(),
                    'thumbnail_image' => $waypoint->getThumbnailUrl(),
                    'position_order' => $waypoint->position_order,
                    'default_yaw' => (float) $waypoint->default_yaw,
                    'default_pitch' => (float) $waypoint->default_pitch,
                    'default_zoom' => (int) $waypoint->default_zoom,
                    'description' => $waypoint->description,
                    'narration' => $waypoint->narration,
                    'linked_room_type_id' => $waypoint->linked_room_type_id,
                    'linked_room_id' => $waypoint->linked_room_id,
                    'room_info_yaw'       => $waypoint->room_info_yaw !== null ? (float) $waypoint->room_info_yaw : null,
                    'room_info_pitch'     => $waypoint->room_info_pitch !== null ? (float) $waypoint->room_info_pitch : null,
                    'is_room_related' => $waypoint->isRoomRelated(),
                    'hotspots' => $waypoint->activeHotspots->map(function ($hotspot) {
                        return [
                            'id' => $hotspot->id,
                            'title' => $hotspot->title,
                            'description' => $hotspot->description,
                            'media_type' => $hotspot->media_type,
                            'media_url' => $hotspot->media_url,
                            'icon' => $hotspot->icon,
                            'pitch' => (float) $hotspot->pitch,
                            'yaw' => (float) $hotspot->yaw,
                            'action_type' => $hotspot->action_type,
                            'action_target' => $hotspot->action_target,
                            'size' => (int) ($hotspot->size ?? 3),
                        ];
                    })->toArray(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $waypoints,
        ]);
    }

    /**
     * Get single waypoint with full details
     */
    public function waypoint(string $slug): JsonResponse
    {
        $waypoint = TourWaypoint::with(['activeHotspots', 'roomType.amenities'])
            ->where('slug', $slug)
            ->active()
            ->first();

        if (!$waypoint) {
            return response()->json([
                'success' => false,
                'message' => 'Waypoint not found',
            ], 404);
        }

        $data = [
            'id' => $waypoint->id,
            'name' => $waypoint->name,
            'slug' => $waypoint->slug,
            'type' => $waypoint->type,
            'type_label' => $waypoint->getTypeLabel(),
            'panorama_image' => $waypoint->getPanoramaUrl(),
            'thumbnail_image' => $waypoint->getThumbnailUrl(),
            'position_order' => $waypoint->position_order,
            'description' => $waypoint->description,
            'narration' => $waypoint->narration,
            'linked_room_type_id' => $waypoint->linked_room_type_id,
            'linked_room_id' => $waypoint->linked_room_id,
            'room_info_yaw'       => $waypoint->room_info_yaw !== null ? (float) $waypoint->room_info_yaw : null,
            'room_info_pitch'     => $waypoint->room_info_pitch !== null ? (float) $waypoint->room_info_pitch : null,
            'is_room_related' => $waypoint->isRoomRelated(),
            'hotspots' => $waypoint->activeHotspots->map(function ($hotspot) {
                return [
                    'id' => $hotspot->id,
                    'title' => $hotspot->title,
                    'description' => $hotspot->description,
                    'media_type' => $hotspot->media_type,
                    'media_url' => $hotspot->media_url,
                    'icon' => $hotspot->icon,
                    'pitch' => (float) $hotspot->pitch,
                    'yaw' => (float) $hotspot->yaw,
                    'action_type' => $hotspot->action_type,
                    'action_target' => $hotspot->action_target,
                    'size' => (int) ($hotspot->size ?? 3),
                ];
            })->toArray(),
        ];

        // Include room type details if linked
        if ($waypoint->roomType) {
            $data['room_type'] = $this->formatRoomTypeData($waypoint->roomType);
        }

        // Navigation helpers
        $data['previous'] = $this->getAdjacentWaypoint($waypoint, 'previous');
        $data['next'] = $this->getAdjacentWaypoint($waypoint, 'next');

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get room type details with real-time availability
     */
    public function roomTypeAvailability(int $id, Request $request): JsonResponse
    {
        $roomType = RoomType::with('amenities')->find($id);

        if (!$roomType || !$roomType->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Room type not found or inactive',
            ], 404);
        }

        $checkIn = $request->get('check_in');
        $checkOut = $request->get('check_out');
        $guests = $request->get('guests', 1);

        $availabilityData = $this->formatRoomTypeData($roomType);

        // Add date-specific availability if dates provided
        if ($checkIn && $checkOut) {
            try {
                $checkInDate = Carbon::parse($checkIn);
                $checkOutDate = Carbon::parse($checkOut);

                $availableRooms = $roomType->availableRoomsForDates($checkInDate, $checkOutDate);
                $availabilityData['available_rooms_count'] = $availableRooms->count();
                $availabilityData['available_rooms'] = $availableRooms->map(fn ($room) => [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'capacity' => $room->capacity,
                    'floor' => $room->floor?->name,
                ]);
            } catch (\Exception $e) {
                $availabilityData['available_rooms_count'] = $roomType->availableRooms()->count();
            }
        } else {
            // Current real-time availability
            $availabilityData['available_rooms_count'] = $roomType->availableRooms()->count();
        }

        $availabilityData['total_rooms_count'] = $roomType->rooms()->count();
        $availabilityData['pricing_display'] = $roomType->getFormattedPrice();

        return response()->json([
            'success' => true,
            'data' => $availabilityData,
        ]);
    }

    /**
     * Get specific room details with real-time availability
     */
    public function roomAvailability(int $id, Request $request): JsonResponse
    {
        try {
            $room = \App\Models\Room::with(['roomType.amenities', 'floor'])->find($id);

            if (!$room || !$room->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found or inactive',
                ], 404);
            }

            $checkIn = $request->get('check_in');
            $checkOut = $request->get('check_out');

            // Determine availability
            $isAvailable = true;
            $unavailableReason = null;

            if ($room->status === 'maintenance' || $room->status === 'inactive') {
                $isAvailable = false;
                $unavailableReason = 'Room is currently under maintenance';
            } elseif ($room->status === 'occupied' && $room->roomType?->isPrivate()) {
                $isAvailable = false;
                $unavailableReason = 'Room is currently occupied';
            } elseif ($room->isFull()) {
                $isAvailable = false;
                $unavailableReason = 'Room is at full capacity';
            } elseif ($checkIn && $checkOut) {
                // Check for date-specific conflicts with holds or assignments
                try {
                    $checkInDate = Carbon::parse($checkIn);
                    $checkOutDate = Carbon::parse($checkOut);

                    $hasConflict = app(RoomHoldService::class)->hasConflict($room, $checkInDate, $checkOutDate);
                    
                    if ($hasConflict) {
                        $isAvailable = false;
                        $unavailableReason = 'Room is reserved for the selected dates';
                    }
                } catch (\Exception $e) {
                    // If date parsing fails, do real-time check only
                }
            }

            $currentOccupancy = $room->roomAssignments()->where('status', 'checked_in')->count();
            $isPrivate = $room->roomType?->isPrivate() ?? false;

            // Get other available rooms of the same type (excluding this room)
            $otherAvailableRooms = [];
            $otherAvailableCount = 0;
            
            if ($room->roomType) {
                $checkInDate = null;
                $checkOutDate = null;
                
                if ($checkIn && $checkOut) {
                    try {
                        $checkInDate = Carbon::parse($checkIn);
                        $checkOutDate = Carbon::parse($checkOut);
                    } catch (\Exception $e) {
                        // Invalid dates, use null
                    }
                }
                
                // Get available rooms for the same type
                if ($checkInDate && $checkOutDate) {
                    // availableRoomsForDates() returns a Collection, use reject() to filter
                    $availableRooms = $room->roomType->availableRoomsForDates($checkInDate, $checkOutDate)
                        ->reject(fn($r) => $r->id === $room->id); // Exclude current room
                } else {
                    // availableRooms() is a relationship, use where() on query builder
                    $availableRooms = $room->roomType->availableRooms()
                        ->with('floor') // Eager load floor to prevent lazy loading error
                        ->where('id', '!=', $room->id) // Exclude current room
                        ->get();
                }
                
                $otherAvailableCount = $availableRooms->count();
                $otherAvailableRooms = $availableRooms->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'room_number' => $r->room_number,
                        'floor' => $r->floor?->name,
                    ];
                })->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'status' => $room->status,
                    'is_available' => $isAvailable,
                    'unavailable_reason' => $unavailableReason,
                    'capacity' => $room->capacity,
                    'current_occupancy' => $currentOccupancy,
                    'available_slots' => max(0, $room->capacity - $currentOccupancy),
                    'floor' => $room->floor?->name,
                    'room_type' => $this->formatRoomTypeData($room->roomType),
                    'is_private_room' => $isPrivate,
                    'other_available_rooms' => $otherAvailableRooms,
                    'other_available_count' => $otherAvailableCount,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Room availability error: ' . $e->getMessage(), [
                'room_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching room availability',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Submit reservation request from tour
     */
    public function reserveSubmit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guest_first_name' => 'required|string|max:255',
            'guest_last_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'guest_age' => 'nullable|integer|min:1|max:120',
            'guest_gender' => 'required|in:Male,Female,Other',
            'guest_address' => 'nullable|string|max:1000',
            'preferred_room_type_id' => 'required|exists:room_types,id',
            'preferred_room_id' => 'nullable|exists:rooms,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'number_of_occupants' => 'required|integer|min:1|max:20',
            'special_requests' => 'nullable|string|max:2000',
            'source' => 'nullable|string|in:virtual_tour',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Combine name fields
        $validated['guest_name'] = trim(
            $validated['guest_first_name'].' '.
            $validated['guest_last_name']
        );

        $validated['status'] = 'pending';
        
        // Build special requests message
        $tourNotice = "\n[Booked via Virtual Tour]";
        if (!empty($validated['preferred_room_id'])) {
            $room = \App\Models\Room::find($validated['preferred_room_id']);
            if ($room) {
                $tourNotice .= "\n[Guest requested specific room: {$room->room_number}]";
            }
        }
        $validated['special_requests'] = ($validated['special_requests'] ?? '') . $tourNotice;

        // source is metadata for validation/context only and is not persisted on reservations.
        unset($validated['source']);
        
        // Save preferred_room_id as metadata in special_requests for staff review
        $preferredRoomId = $validated['preferred_room_id'] ?? null;
        unset($validated['preferred_room_id']);

        $reservation = Reservation::create($validated);

        // Store preferred room as metadata (no hold created - staff will review)
        if ($preferredRoomId) {
            $room = \App\Models\Room::find($preferredRoomId);
            if ($room) {
                // Update reservation to include preferred room metadata
                $reservation->update([
                    'special_requests' => ($reservation->special_requests ?? '') 
                        . "\n[Preferred Room ID: {$preferredRoomId}]",
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Reservation submitted successfully!',
            'data' => [
                'reference_number' => $reservation->reference_number,
                'track_url' => route('guest.track', ['reference' => $reservation->reference_number]),
            ],
        ]);
    }

    /**
     * Format room type data for tour API responses
     */
    protected function formatRoomTypeData(?RoomType $roomType): ?array
    {
        if (!$roomType) {
            return null;
        }
        
        return [
            'id' => $roomType->id,
            'name' => $roomType->name,
            'description' => $roomType->description,
            'base_rate' => $roomType->base_rate,
            'pricing_type' => $roomType->pricing_type,
            'room_sharing_type' => $roomType->room_sharing_type,
            'formatted_price' => $roomType->getFormattedPrice(),
            'is_private' => $roomType->isPrivate(),
            'is_public' => $roomType->isPublic(),
            'images' => $roomType->images ?? [],
            'primary_image' => is_array($roomType->images) && count($roomType->images) > 0
                ? asset('storage/'.$roomType->images[0])
                : null,
            'amenities' => $roomType->amenities->map(fn ($amenity) => [
                'id' => $amenity->id,
                'name' => $amenity->name,
                'description' => $amenity->description,
            ])->toArray(),
        ];
    }

    /**
     * Get adjacent waypoint (previous or next)
     */
    protected function getAdjacentWaypoint(TourWaypoint $current, string $direction): ?array
    {
        $query = TourWaypoint::active()->ordered();

        if ($direction === 'previous') {
            $adjacent = $query->where('position_order', '<', $current->position_order)
                ->orderBy('position_order', 'desc')
                ->first();
        } else {
            $adjacent = $query->where('position_order', '>', $current->position_order)
                ->orderBy('position_order', 'asc')
                ->first();
        }

        if (!$adjacent) {
            return null;
        }

        return [
            'id' => $adjacent->id,
            'name' => $adjacent->name,
            'slug' => $adjacent->slug,
            'type' => $adjacent->type,
            'type_label' => $adjacent->getTypeLabel(),
        ];
    }
}
