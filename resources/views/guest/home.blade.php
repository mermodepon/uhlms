@extends('layouts.guest')

@section('title', 'Welcome')

@section('content')
    {{-- Hero Section --}}
    @php
        $welcomeMessage = 'Comfortable and affordable lodging for visiting scholars, faculty, students, and guests of Central Mindanao University.';
        $siteTitle      = 'University Homestay';
    @endphp
    <section class="relative bg-gradient-to-br from-[#00491E] via-[#02681E] to-[#00491E] text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                {{-- Left: Text --}}
                <div>
                    <span class="inline-block bg-[#FFC600] text-[#00491E] px-3 py-1 rounded-full text-xs font-bold mb-5 uppercase tracking-widest">360° Virtual Tour Available</span>
                    <h1 class="text-4xl md:text-5xl font-bold mb-5 leading-tight">{{ $siteTitle }}</h1>
                    <p class="text-lg text-gray-200 mb-8 max-w-lg">{{ $welcomeMessage }}</p>
                    <ul class="space-y-3 mb-8 text-gray-200">
                        <li class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[#FFC600] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            Navigate freely between rooms and common areas
                        </li>
                        <li class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[#FFC600] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            View room details and real-time availability
                        </li>
                        <li class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[#FFC600] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            Request a reservation directly from within the tour
                        </li>
                    </ul>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('guest.tour.viewer') }}" class="inline-flex items-center justify-center gap-2 bg-[#FFC600] text-[#00491E] px-8 py-3.5 rounded-lg font-bold text-lg hover:bg-yellow-400 transition shadow-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Start Virtual Tour
                        </a>
                        <a href="{{ route('guest.rooms') }}" class="inline-flex items-center justify-center gap-2 bg-white/10 border border-white/30 text-white px-8 py-3.5 rounded-lg font-bold text-lg hover:bg-white/20 transition">
                            Browse Rooms
                        </a>
                    </div>
                </div>
                {{-- Right: Preview Card --}}
                <div class="flex items-center justify-center">
                    <div class="relative w-full max-w-md">
                        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 shadow-2xl">
                            <a href="{{ route('guest.tour.viewer') }}" class="block rounded-xl overflow-hidden bg-black group cursor-pointer" style="aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;position:relative;">
                                @if($previewWaypoint && $previewWaypoint->panorama_image)
                                    <img src="{{ asset('storage/' . $previewWaypoint->panorama_image) }}" alt="Virtual Tour Preview" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-br from-[#02681E]/70 to-black/80 flex flex-col items-center justify-center transition-opacity duration-300 group-hover:opacity-90">
                                    <svg class="w-16 h-16 text-[#FFC600] mb-3 transition-transform duration-300 group-hover:scale-110" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                    <p class="text-white font-semibold text-lg">360° Interactive Tour</p>
                                    <p class="text-gray-300 text-sm mt-1">Navigate. Explore. Reserve.</p>
                                    <div class="mt-3 px-4 py-2 bg-[#FFC600] text-[#00491E] rounded-full font-semibold text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300">Click to Explore →</div>
                                </div>
                            </a>
                            <div class="mt-4 flex items-center justify-between text-sm text-white/70">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 bg-[#FFC600] rounded-full inline-block"></span>
                                    Live tour available
                                </span>
                                <span>Works on mobile &amp; desktop</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-gray-50 to-transparent"></div>
    </section>

    {{-- About & Amenities --}}
    @php
        $aboutText     = null;
        $showAmenities = false;
        $amenities     = [];
    @endphp
    @if($aboutText)
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-[#00491E] mb-4">About Us</h2>
            <p class="text-gray-700 max-w-2xl mx-auto">{{ $aboutText }}</p>
        </div>
    </section>
    @endif
    @if($showAmenities && is_array($amenities) && count($amenities))
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-[#00491E] mb-4">Amenities</h2>
            <div class="flex flex-wrap justify-center gap-6">
                @foreach($amenities as $amenity)
                    <div class="bg-white rounded-lg shadow p-4 flex flex-col items-center w-40">
                        @if(!empty($amenity['image']))
                            <img src="{{ asset('storage/' . $amenity['image']) }}" alt="{{ $amenity['name'] }}" class="h-16 w-16 object-cover rounded mb-2" />
                        @endif
                        <span class="font-semibold text-[#00491E]">{{ $amenity['name'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- Room Types Preview --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-[#00491E] mb-4">Our Accommodations</h2>
            <p class="text-gray-600 max-w-2xl mx-auto">Choose from a variety of room types designed to meet your needs and budget during your stay at CMU.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse($roomTypes as $roomType)
                <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition group">
                    @if($roomType->images && count($roomType->images))
                        <div class="h-48 bg-gray-200 overflow-hidden">
                            <img src="{{ asset('storage/' . collect($roomType->images)->first()) }}" alt="{{ $roomType->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        </div>
                    @else
                        <div class="h-48 bg-gradient-to-br from-[#00491E] to-[#02681E] flex items-center justify-center">
                            <svg class="w-16 h-16 text-[#FFC600]/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                            </svg>
                        </div>
                    @endif
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-xl font-bold text-[#00491E]">{{ $roomType->name }}</h3>
                            <span class="bg-[#FFC600] text-[#00491E] px-3 py-1 rounded-full text-sm font-bold">
                                {{ $roomType->getFormattedPrice() }}
                            </span>
                        </div>
                        <p class="text-gray-600 text-sm mb-3">{{ Str::limit($roomType->description, 100) }}</p>
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Up to {{ $roomType->capacity }} {{ Str::plural('guest', $roomType->capacity) }}
                            <span class="mx-2">|</span>
                            <span class="text-[#02681E] font-medium">{{ $roomType->available_rooms_count }} available</span>
                        </div>
                        @if($roomType->amenities->count())
                            <div class="flex flex-wrap gap-1 mb-4">
                                @foreach($roomType->amenities->take(4) as $amenity)
                                    <span class="bg-[#00491E]/10 text-[#00491E] px-2 py-0.5 rounded text-xs">{{ $amenity->name }}</span>
                                @endforeach
                                @if($roomType->amenities->count() > 4)
                                    <span class="text-gray-400 text-xs">+{{ $roomType->amenities->count() - 4 }} more</span>
                                @endif
                            </div>
                        @endif
                        <a href="{{ route('guest.room-detail', $roomType) }}" class="text-[#02681E] font-semibold hover:text-[#00491E] transition text-sm">
                            View Details &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12 text-gray-500">
                    <p>No room types available at the moment. Please check back later.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="py-16 bg-gradient-to-b from-white to-[#00491E]/5 border-y border-[#00491E]/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <span class="inline-flex items-center rounded-full bg-[#FFC600]/20 px-4 py-1 text-xs font-bold uppercase tracking-[0.2em] text-[#00491E]">
                    Stay Guide
                </span>
                <h2 class="text-3xl font-bold text-[#00491E] mt-4 mb-3">Stay Inclusions &amp; Optional Add-ons</h2>
                <p class="text-gray-600 max-w-3xl mx-auto">
                    A quick overview of what guests commonly enjoy during their stay and the extra services that may be arranged when needed.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-md border border-[#00491E]/10 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-[#00491E] flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#FFC600]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-[#00491E]">Included in Most Stays</h3>
                            <p class="text-sm text-gray-500">Core essentials guests can usually expect.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($stayInclusions as $item)
                            <div class="rounded-xl bg-[#00491E]/5 border border-[#00491E]/10 px-4 py-3 text-sm text-[#00491E] font-medium flex items-start gap-3">
                                <span class="mt-0.5 text-[#02681E]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </span>
                                <span>
                                    {{ $item->name }}
                                    @if($item->description)
                                        <span class="block mt-1 text-xs font-normal text-gray-500">{{ $item->description }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                        @if($stayInclusions->isEmpty())
                            <div class="sm:col-span-2 rounded-xl bg-[#00491E]/5 border border-dashed border-[#00491E]/20 px-4 py-4 text-sm text-gray-500">
                                Included stay highlights will appear here as active room amenities are configured.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-[#00491E] rounded-2xl shadow-md border border-[#02681E] p-8 text-white">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-[#FFC600] text-[#00491E] flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold">Available Upon Request</h3>
                            <p class="text-sm text-white/70">Helpful extras that may be arranged in advance.</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($optionalAddOns as $item)
                            <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-sm font-medium flex items-start gap-3">
                                <span class="mt-0.5 text-[#FFC600]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
                                    </svg>
                                </span>
                                <span class="flex-1">
                                    <span class="block">{{ $item->name }}</span>
                                    @if($item->description)
                                        <span class="block mt-1 text-xs font-normal text-white/70">{{ $item->description }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 rounded-full bg-[#FFC600] px-2.5 py-1 text-xs font-bold text-[#00491E]">
                                    {{ $item->formatted_price }}
                                </span>
                            </div>
                        @endforeach
                        @if($optionalAddOns->isEmpty())
                            <div class="rounded-xl bg-white/10 border border-dashed border-white/20 px-4 py-4 text-sm text-white/70">
                                Optional add-ons will appear here as active services are configured.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="inline-flex items-center justify-center rounded-full bg-white border border-[#00491E]/10 px-5 py-2 text-sm text-gray-600 shadow-sm">
                    Availability may vary by room type, season, and reservation arrangement. Room detail pages remain the best source for exact inclusions.
                </p>
            </div>
        </div>
    </section>

    {{-- Booking Policy & FAQ --}}
    @php
        $bookingPolicy = null;
        $faq           = [];
    @endphp
    @if($bookingPolicy)
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-[#00491E] mb-4">Booking Policy & Terms</h2>
            <p class="text-gray-700 max-w-2xl mx-auto">{{ $bookingPolicy }}</p>
        </div>
    </section>
    @endif
    @if(is_array($faq) && count($faq))
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-[#00491E] mb-4">Frequently Asked Questions</h2>
            <div class="max-w-2xl mx-auto text-left">
                @foreach($faq as $item)
                    <div class="mb-6">
                        <div class="font-semibold text-[#00491E]">Q: {{ $item['question'] }}</div>
                        <div class="text-gray-700 ml-2">A: {{ $item['answer'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif
    <section class="bg-[#00491E]/5 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-[#00491E] mb-4">How to Reserve</h2>
                <p class="text-gray-600">Simple steps to book your stay at CMU University Homestay</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                @php
                    $steps = [
                        ['icon' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z', 'title' => 'Browse Rooms', 'desc' => 'Explore our room types and take virtual tours'],
                        ['icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z', 'title' => 'Submit Reservation', 'desc' => 'Fill out the reservation form with your details'],
                        ['icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Get Approved', 'desc' => 'Staff will review and approve your request'],
                        ['icon' => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z', 'title' => 'Check In', 'desc' => 'Room assigned and ready for your arrival'],
                    ];
                @endphp
                @foreach($steps as $i => $step)
                    <div class="text-center">
                        <div class="w-16 h-16 bg-[#00491E] rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-[#FFC600]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $step['icon'] }}"/>
                            </svg>
                        </div>
                        <div class="text-[#FFC600] font-bold text-sm mb-1">Step {{ $i + 1 }}</div>
                        <h3 class="font-bold text-[#00491E] mb-2">{{ $step['title'] }}</h3>
                        <p class="text-gray-600 text-sm">{{ $step['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
