@extends('layouts.guest')

@section('title', $roomType->name)

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="text-sm mb-4">
                <a href="{{ route('guest.rooms') }}" class="text-gray-300 hover:text-[#FFC600] transition">Rooms</a>
                <span class="text-gray-400 mx-2">/</span>
                <span class="text-[#FFC600]">{{ $roomType->name }}</span>
            </nav>
            <h1 class="text-3xl font-bold mb-2">{{ $roomType->name }}</h1>
            <div class="flex items-center gap-4 text-gray-200">
                <span>{{ $roomType->getFormattedPrice() }}</span>
                <span>•</span>
                <span>Up to {{ $roomType->capacity }} {{ Str::plural('guest', $roomType->capacity) }}</span>
                <span>•</span>
                <span class="text-[#FFC600] font-medium">{{ $roomType->available_rooms_count }} rooms available</span>
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
                                     src="{{ asset('storage/' . $image) }}"
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
                                        <img src="{{ asset('storage/' . $image) }}"
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

                {{-- Virtual Tour --}}
                @if($roomType->virtual_tour_url)
                    <div id="virtual-tour" class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-[#00491E] mb-4">🎯 360° Virtual Tour</h2>
                        <p class="text-gray-600 text-sm mb-4">Explore this room type in an immersive 360° virtual tour. Click and drag to look around.</p>
                        <div class="rounded-lg overflow-hidden" style="position: relative; padding-bottom: 56.25%; height: 0;">
                            <iframe
                                src="{{ $roomType->virtual_tour_url }}"
                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                                allowfullscreen
                                loading="lazy"
                            ></iframe>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Booking Card --}}
                <div class="bg-white rounded-xl shadow-md p-6 sticky top-6">
                    <div class="text-center mb-6">
                        <div class="text-3xl font-bold text-[#00491E]">₱{{ number_format($roomType->base_rate, 0) }}</div>
                        <div class="text-gray-500">{{ $roomType->isPerPersonPricing() ? 'per person per night' : 'per night' }}</div>
                    </div>
                    <div class="space-y-3 text-sm text-gray-600 mb-6">
                        <div class="flex justify-between">
                            <span>Capacity</span>
                            <span class="font-medium">{{ $roomType->capacity }} {{ Str::plural('guest', $roomType->capacity) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Available Rooms</span>
                            <span class="font-medium text-[#02681E]">{{ $roomType->available_rooms_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total Rooms</span>
                            <span class="font-medium">{{ $roomType->rooms_count }}</span>
                        </div>
                    </div>
                    <a href="{{ route('guest.reserve', ['room_type' => $roomType->id]) }}"
                       class="block w-full bg-[#FFC600] text-[#00491E] text-center px-6 py-3 rounded-lg font-bold hover:bg-yellow-400 transition">
                        Reserve This Room
                    </a>
                    <a href="{{ route('guest.rooms') }}" class="block text-center text-sm text-gray-500 hover:text-[#00491E] mt-3 transition">
                        ← Back to All Rooms
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
