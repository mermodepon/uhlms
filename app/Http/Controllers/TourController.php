<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\RoomHold;
use App\Models\TourWaypoint;
use App\Models\RoomType;
use App\Services\RoomHoldService;
use App\Support\MediaUrl;
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
        $waypoints = TourWaypoint::with(['activeHotspots', 'room:id,room_type_id'])
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
                    'linked_room_type_id' => $waypoint->linked_room_type_id ?: $waypoint->room?->room_type_id,
                    'linked_room_id' => null,
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
        $waypoint = TourWaypoint::with(['activeHotspots', 'roomType.amenities', 'room:id,room_type_id'])
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
            'linked_room_type_id' => $waypoint->linked_room_type_id ?: $waypoint->room?->room_type_id,
            'linked_room_id' => null,
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

                $availabilityData['available_rooms_count'] = $roomType
                    ->availableRoomsForDates($checkInDate, $checkOutDate)
                    ->count();
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
     * Get aggregate room type availability for a room-linked waypoint.
     *
     * This endpoint intentionally does not disclose whether the specific room
     * behind the waypoint is available. Guests only receive room type counts.
     */
    public function roomAvailability(int $id, Request $request): JsonResponse
    {
        try {
            $room = \App\Models\Room::with(['roomType.amenities'])->find($id);

            if (!$room || !$room->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found or inactive',
                ], 404);
            }

            if (!$room->roomType || !$room->roomType->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room type not found or inactive',
                ], 404);
            }

            $requestForType = Request::create('', 'GET', $request->only(['check_in', 'check_out', 'guests']));
            $response = $this->roomTypeAvailability($room->roomType->id, $requestForType);
            $payload = $response->getData(true);

            return response()->json([
                'success' => true,
                'data' => $payload['data'] ?? $this->formatRoomTypeData($room->roomType),
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
        $validated['preferred_room_id'] = null;

        // Combine name fields
        $validated['guest_name'] = trim(
            $validated['guest_first_name'].' '.
            $validated['guest_last_name']
        );

        $validated['status'] = 'pending';
        
        // Build special requests message
        $tourNotice = "\n[Booked via Virtual Tour]";
        $validated['special_requests'] = ($validated['special_requests'] ?? '') . $tourNotice;

        // source is metadata for validation/context only and is not persisted on reservations.
        unset($validated['source']);
        
        unset($validated['preferred_room_id']);

        $reservation = Reservation::create($validated);

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
                ? MediaUrl::url($roomType->images[0])
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
