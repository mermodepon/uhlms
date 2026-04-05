@extends('layouts.guest')

@section('title', 'Welcome')

@section('content')
    {{-- Hero Section --}}
    @php
        $heroSrc          = asset('images/uh_banner.png');
        $heroEmbed        = null;
        $heroEmbedEnabled = false;
        $welcomeMessage   = 'Comfortable and affordable lodging for visiting scholars, faculty, students, and guests of Central Mindanao University.';
        $siteTitle        = 'University Homestay';
    @endphp
    <section class="relative bg-gradient-to-br from-[#00491E] via-[#02681E] to-[#00491E] text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
            <div class="flex justify-center mb-8">
                @if($heroEmbed && $heroEmbedEnabled)
                    <div class="w-full max-w-4xl rounded-xl shadow-lg overflow-hidden" style="position: relative; padding-bottom: 56.25%; height: 0;">
                        <iframe src="{{ $heroEmbed }}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" allowfullscreen loading="lazy"></iframe>
                    </div>
                @else
                    <img src="{{ $heroSrc }}" alt="Hero Banner" class="w-full max-w-4xl rounded-xl shadow-lg object-cover" />
                @endif
            </div>
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-6">
                    {{ $siteTitle }}
                </h1>
                <p class="text-xl md:text-2xl text-gray-200 mb-8 max-w-3xl mx-auto">
                    {{ $welcomeMessage }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('guest.rooms') }}" class="bg-[#FFC600] text-[#00491E] px-8 py-3 rounded-lg font-bold text-lg shadow-lg transition-all duration-200 hover:bg-[#00491E] hover:text-[#FFC600] hover:shadow-2xl hover:scale-105 active:scale-95">
                        Browse Rooms
                    </a>
                    <a href="{{ route('guest.reserve') }}" class="bg-[#FFC600] text-[#00491E] px-8 py-3 rounded-lg font-bold text-lg shadow-lg transition-all duration-200 hover:bg-[#00491E] hover:text-[#FFC600] hover:shadow-2xl hover:scale-105 active:scale-95">
                        Make a Reservation
                    </a>
                    <a href="{{ route('guest.virtual-tours') }}" class="bg-[#FFC600] text-[#00491E] px-8 py-3 rounded-lg font-bold text-lg shadow-lg transition-all duration-200 hover:bg-[#00491E] hover:text-[#FFC600] hover:shadow-2xl hover:scale-105 active:scale-95 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline-block w-5 h-5 align-middle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> 360° Virtual Tours
                    </a>
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
