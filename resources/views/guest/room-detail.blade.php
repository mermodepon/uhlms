@extends('layouts.guest')

@section('title', $roomType->name)

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="text-sm mb-4">
                <a href="{{ route('guest.rooms', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">Rooms</a>
                <span class="text-gray-400 mx-2">/</span>
                <span class="text-[#FFC600]">{{ $roomType->name }}</span>
            </nav>
            <h1 class="text-3xl font-bold mb-2">{{ $roomType->name }}</h1>
            @php
                $isPrivate = $roomType->isPrivate();
                $totalRooms = $roomType->rooms_count;
                $availableRooms = $roomType->available_rooms_count;
                
                // Calculate aggregate bed availability for shared rooms
                if (!$isPrivate) {
                    $totalBeds = $roomType->rooms()->sum('capacity');
                    $availableBeds = $roomType->rooms()->get()->sum(function ($room) {
                        $checkedIn = $room->roomAssignments()->where('status', 'checked_in')->count();
                        return max(0, ($room->capacity ?? 0) - $checkedIn);
                    });
                } else {
                    $totalBeds = 0;
                    $availableBeds = 0;
                }
            @endphp
            <div class="flex items-center gap-4 text-gray-200">
                <span>{{ $roomType->getFormattedPrice() }}</span>
                <span>•</span>
                <span>Up to {{ $roomType->capacity }} {{ Str::plural('guest', $roomType->capacity) }}</span>
                <span>•</span>
                <span class="text-[#FFC600] font-medium">
                    @if($isPrivate)
                        {{ $availableRooms }} of {{ $totalRooms }} rooms available
                    @else
                        {{ $availableBeds }} of {{ $totalBeds }} beds available
                    @endif
                </span>
            </div>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-8">
                {{-- Images Gallery --}}
                @if($roomType->images && count($roomType->images))
                    <div x-data="{ activeImage: 0 }" class="space-y-3">
                        {{-- Main Image --}}
                        <div class="rounded-xl overflow-hidden shadow-md">
                            @foreach($roomType->images as $index => $image)
                                <img x-show="activeImage === {{ $index }}"
                                     src="{{ \App\Support\MediaUrl::url($image) }}"
                                     alt="{{ $roomType->name }} - Image {{ $index + 1 }}"
                                     class="w-full h-80 object-cover">
                            @endforeach
                        </div>
                        {{-- Thumbnails --}}
                        @if(count($roomType->images) > 1)
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                @foreach($roomType->images as $index => $image)
                                    <button @click="activeImage = {{ $index }}"
                                            :class="activeImage === {{ $index }} ? 'ring-2 ring-[#FFC600] opacity-100' : 'opacity-60 hover:opacity-90'"
                                            class="flex-shrink-0 rounded-lg overflow-hidden transition w-20 h-16">
                                        <img src="{{ \App\Support\MediaUrl::url($image) }}"
                                             alt="Thumbnail {{ $index + 1 }}"
                                             class="w-full h-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Description --}}
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-[#00491E] mb-4">About This Room Type</h2>
                    <p class="text-gray-600 leading-relaxed">{{ $roomType->description ?: 'No description available.' }}</p>
                </div>

                {{-- Amenities --}}
                @if($roomType->amenities->count())
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-[#00491E] mb-4">Amenities</h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($roomType->amenities as $amenity)
                                <div class="flex items-center gap-2 text-gray-700">
                                    <svg class="w-5 h-5 text-[#919F02]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span>{{ $amenity->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

            {{-- Sidebar --}}
            <div class="space-y-6 lg:sticky lg:top-6 self-start">
                {{-- Booking Card --}}
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="text-center mb-6">
                        <div class="text-3xl font-bold text-[#00491E]">₱{{ number_format($roomType->base_rate, 0) }}</div>
                        <div class="text-gray-500">{{ $roomType->isPerPersonPricing() ? 'per person per night' : 'per night' }}</div>
                    </div>

                    {{-- Availability Summary --}}
                    @php
                        $availabilityPercent = $isPrivate
                            ? ($totalRooms > 0 ? round(($availableRooms / $totalRooms) * 100) : 0)
                            : ($totalBeds > 0 ? round(($availableBeds / $totalBeds) * 100) : 0);
                    @endphp

                    <div class="mb-6 pb-6 border-b border-gray-200">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">{{ $isPrivate ? 'Available Rooms' : 'Available Beds' }}</span>
                            <span class="text-lg font-bold text-[#02681E]">
                                @if($isPrivate)
                                    {{ $availableRooms }} of {{ $totalRooms }}
                                @else
                                    {{ $availableBeds }} of {{ $totalBeds }}
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-[#02681E] h-2 rounded-full transition-all" style="width: {{ $availabilityPercent }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">{{ $availabilityPercent }}% {{ $isPrivate ? 'rooms' : 'beds' }} available</p>
                    </div>

                    <div class="space-y-3 text-sm text-gray-600 mb-6">
                        <div class="flex justify-between">
                            <span>Capacity per Room</span>
                            <span class="font-medium">{{ $roomType->capacity }} {{ Str::plural('guest', $roomType->capacity) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total Rooms</span>
                            <span class="font-medium">{{ $roomType->rooms_count }}</span>
                        </div>
                    </div>

                    @if(($isPrivate && $availableRooms > 0) || (! $isPrivate && $availableBeds > 0))
                        <a href="{{ route('guest.reserve', ['room_type' => $roomType->id], false) }}"
                           class="block w-full bg-[#FFC600] text-[#00491E] text-center px-6 py-3 rounded-lg font-bold hover:bg-yellow-400 transition">
                            Reserve This Room
                        </a>
                    @else
                        <div class="block w-full bg-gray-300 text-gray-600 text-center px-6 py-3 rounded-lg font-bold cursor-not-allowed">
                            {{ $isPrivate ? 'No Rooms Available' : 'No Beds Available' }}
                        </div>
                    @endif
                    
                    <a href="{{ route('guest.rooms', [], false) }}" class="block text-center text-sm text-gray-500 hover:text-[#00491E] mt-3 transition">
                        ← Back to All Rooms
                    </a>
                </div>

                {{-- Virtual Tour Card --}}
                <div class="bg-gradient-to-br from-[#00491E] to-[#02681E] rounded-xl p-5 text-white text-center shadow-md">
                    <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-[#FFC600]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-1">See It in 360°</h3>
                    <p class="text-gray-200 text-sm mb-4">
                        {{ $tourWaypointSlug ? 'Jump straight into this room in the interactive virtual tour before you book.' : 'Explore the establishment in an interactive virtual tour before you book.' }}
                    </p>
                    <a href="{{ route('guest.tour.viewer', $tourWaypointSlug ? ['slug' => $tourWaypointSlug] : [], false) }}" class="inline-block w-full bg-[#FFC600] text-[#00491E] font-bold py-2.5 px-4 rounded-lg hover:bg-yellow-400 transition text-sm">
                        {{ $tourWaypointSlug ? 'View This Room in 360° →' : 'Start Virtual Tour →' }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
