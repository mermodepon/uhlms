<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\RoomHold;
use App\Models\TourWaypoint;
use App\Models\RoomType;
use App\Services\RoomHoldService;
use App\Support\MediaUrl;
use App\Support\ReservationRoomRequests;
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
        $startWaypoint = $slug;
        $waypointCount = TourWaypoint::query()->active()->count();
        $hasWaypoints = $waypointCount > 0;

        return view('guest.virtual-tour-viewer', compact('startWaypoint', 'hasWaypoints', 'waypointCount'));
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
        $requestedGuests = max(1, (int) $guests);

        // Add date-specific availability if dates provided
        if ($checkIn && $checkOut) {
            try {
                $checkInDate = Carbon::parse($checkIn);
                $checkOutDate = Carbon::parse($checkOut);
                $availabilityData = array_merge(
                    $availabilityData,
                    app(RoomHoldService::class)->getDateAvailabilitySummary($roomType, $checkInDate, $checkOutDate, $requestedGuests)
                );
            } catch (\Exception $e) {
                $availabilityData = array_merge(
                    $availabilityData,
                    app(RoomHoldService::class)->getCurrentAvailabilitySummary($roomType, $requestedGuests)
                );
            }
        } else {
            $availabilityData = array_merge(
                $availabilityData,
                app(RoomHoldService::class)->getCurrentAvailabilitySummary($roomType, $requestedGuests)
            );
        }

        $availabilityData['pricing_display'] = $roomType->getFormattedPrice();
        $availabilityData['requested_guests'] = $requestedGuests;
        unset($availabilityData['available_rooms']);

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
        $request->merge([
            'guest_first_name' => trim((string) $request->input('guest_first_name', '')),
            'guest_last_name' => trim((string) $request->input('guest_last_name', '')),
            'guest_phone' => trim((string) $request->input('guest_phone', '')) ?: null,
        ]);

        $validator = Validator::make($request->all(), [
            'guest_first_name' => 'required|string|max:255',
            'guest_last_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => ['required', 'string', 'max:20', 'regex:/^(09\d{9}|\+639\d{9}|639\d{9})$/'],
            'guest_age' => 'required|integer|min:18|max:120',
            'guest_gender' => 'required|in:Male,Female,Other',
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
            'special_requests' => 'nullable|string|max:2000',
            'source' => 'nullable|string|in:virtual_tour',
            'availability_acknowledged' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $roomRequestLines = ReservationRoomRequests::fromRequest($request);
        $requestValidation = ReservationRoomRequests::validateAvailability(
            $roomRequestLines,
            $validated['check_in_date'],
            $validated['check_out_date']
        );

        if (! empty($requestValidation['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => collect($requestValidation['errors'])->map(fn ($message) => [$message])->all(),
            ], 422);
        }

        // Combine name fields
        $validated['guest_name'] = trim(
            $validated['guest_first_name'].' '.
            $validated['guest_last_name']
        );

        $validated['status'] = 'pending';

        if (! empty($requestValidation['warnings'])) {
            $warning = implode(' ', $requestValidation['warnings'])
                .' You can still submit this request for staff review by confirming the warning.';

            if (! $request->boolean('availability_acknowledged')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Availability confirmation required',
                'requires_availability_confirmation' => true,
                'availability_warning' => $warning,
                'availability_summary' => [
                        'availability_label' => implode(' ', $requestValidation['warnings']),
                        'can_accommodate_requested_guests' => false,
                    ],
                ], 422);
            }

            $validated['special_requests'] = trim(
                (($validated['special_requests'] ?? null) ? rtrim($validated['special_requests'])."\n" : '')
                .'[Availability warning acknowledged by guest: '
                .implode(' ', $requestValidation['warnings'])
                .' Requested: '.$requestValidation['summary'].' on '
                .$validated['check_in_date'].' to '.$validated['check_out_date'].']'
            );
        }
        
        // Build special requests message
        $tourNotice = "\n[Reservation request submitted via Virtual Tour]";
        $validated['special_requests'] = ($validated['special_requests'] ?? '') . $tourNotice;

        // source is metadata for validation/context only and is not persisted on reservations.
        unset($validated['source']);
        unset($validated['availability_acknowledged']);
        unset($validated['requested_room_count'], $validated['room_requests']);

        $validated = ReservationRoomRequests::applyToReservationData($validated, $roomRequestLines);

        $reservation = Reservation::create($validated);
        ReservationRoomRequests::persist($reservation, $roomRequestLines);

        return response()->json([
            'success' => true,
            'message' => 'Reservation request submitted successfully!',
            'data' => [
                'reference_number' => $reservation->reference_number,
                'track_url' => $reservation->generateGuestTrackingUrl(),
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
            'capacity' => (int) ($roomType->capacity ?? 1),
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
     * @return array{room_type: RoomType, summary: array<string,mixed>}|null
     */
    protected function resolveAvailabilityContext(int $roomTypeId, string $checkInDate, string $checkOutDate, int $occupants): ?array
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
    protected function resolveOccupantLimit(int $roomTypeId, string $checkInDate, string $checkOutDate): ?array
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
