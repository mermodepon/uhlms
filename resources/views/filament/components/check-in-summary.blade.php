@php
    $reservationRooms = $getState() ?? [];
@endphp

@if(!empty($reservationRooms))
    <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4">
        <h3 class="mb-3 text-sm font-semibold text-green-900">
            ✓ Room Assignment Summary
        </h3>
        <div class="space-y-2">
            @foreach($reservationRooms as $index => $roomEntry)
                @php
                    $roomId = $roomEntry['room_id'] ?? null;
                    $roomMode = $roomEntry['room_mode'] ?? 'unknown';
                    $guests = $roomEntry['guests'] ?? [];
                    $room = $roomId ? \App\Models\Room::find($roomId) : null;
                @endphp
                
                @if($room && !empty($guests))
                    <div class="rounded border border-green-300 bg-white p-3">
                        <div class="mb-2 flex items-center gap-2">
                            <span class="text-xs font-bold text-green-700">Room {{ $room->room_number }}</span>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                                {{ ucfirst($roomMode) }}
                            </span>
                            <span class="text-xs text-gray-600">
                                {{ $room->roomType->name }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-700">
                            <strong>{{ count($guests) }}</strong> guest(s):
                            {{ implode(', ', array_map(fn($g) => trim(($g['first_name'] ?? '') . ' ' . ($g['last_name'] ?? '')), $guests)) }}
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
