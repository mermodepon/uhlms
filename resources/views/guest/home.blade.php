@extends('layouts.guest')

@section('title', 'Welcome')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    {{-- Hero Section --}}
    @php
        $guestSite = \App\Support\GuestSiteSettings::all();
        $welcomeMessage = $guestSite['guest_hero_message'];
        $siteTitle = $guestSite['guest_hero_headline'];
        $guestDateDefaults = \App\Support\GuestDatePolicy::defaults(request('check_in'), request('check_out'));
        $defaultCheckIn = $guestDateDefaults['check_in'];
        $defaultCheckOut = $guestDateDefaults['check_out'];
        $heroBullets = array_filter($guestSite['guest_hero_bullets'] ?? []);
        $heroBackgroundUrl = \App\Support\GuestSiteSettings::heroBackgroundUrl();
        $heroOverlayOpacity = \App\Support\GuestSiteSettings::heroBackgroundOverlayOpacity();
        $heroImageVisibility = max(0.25, min(0.65, 1 - ($heroOverlayOpacity / 2)));
    @endphp
    <section class="relative overflow-hidden bg-gradient-to-br from-[#00491E] via-[#02681E] to-[#00491E] text-white">
        @if($heroBackgroundUrl)
            <img
                src="{{ $heroBackgroundUrl }}"
                alt=""
                class="absolute inset-0 h-full w-full object-cover"
                style="opacity: {{ $heroImageVisibility }};"
                aria-hidden="true"
            >
            <div class="absolute inset-0" style="background: linear-gradient(90deg, rgba(0, 35, 14, 0.82) 0%, rgba(0, 54, 21, 0.66) 42%, rgba(0, 54, 21, 0.48) 62%, rgba(0, 35, 14, 0.62) 100%);"></div>
        @endif
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16 lg:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-10 lg:gap-12 items-start lg:items-center">
                {{-- Left: Text --}}
                <div>
                    @if($guestSite['guest_hero_badge'])
                        <span class="inline-block bg-[#FFC600] text-[#00491E] px-3 py-1 rounded-full text-xs font-bold mb-4 md:mb-5 uppercase tracking-widest">{{ $guestSite['guest_hero_badge'] }}</span>
                    @endif
                    <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold mb-3 sm:mb-4 md:mb-5 leading-tight">{{ $siteTitle }}</h1>
                    <p class="text-sm sm:text-base md:text-lg text-gray-200 mb-5 sm:mb-6 md:mb-8 max-w-lg">{{ $welcomeMessage }}</p>
                    @if(count($heroBullets))
                        <ul class="space-y-2 sm:space-y-2.5 md:space-y-3 mb-5 sm:mb-6 md:mb-8 text-xs sm:text-sm md:text-base text-gray-200">
                            @foreach($heroBullets as $bullet)
                                <li class="flex items-center gap-2 sm:gap-2.5 md:gap-3">
                                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 md:w-5 md:h-5 text-[#FFC600] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    {{ is_array($bullet) ? ($bullet['text'] ?? '') : $bullet }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="flex flex-col sm:flex-row gap-2.5 md:gap-3">
                        @if($guestSite['guest_show_virtual_tour_cta'])
                        <a href="{{ route('guest.tour.viewer', [], false) }}" class="inline-flex items-center justify-center gap-2 bg-[#FFC600] text-[#00491E] px-5 sm:px-6 md:px-8 py-2.5 sm:py-3 md:py-3.5 rounded-lg font-bold text-sm sm:text-base md:text-lg hover:bg-yellow-400 transition shadow-lg">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            {{ $guestSite['guest_hero_primary_cta_label'] }}
                        </a>
                        @endif
                        <a href="{{ route('guest.rooms', [], false) }}" class="inline-flex items-center justify-center gap-2 bg-white/10 border border-white/30 text-white px-5 sm:px-6 md:px-8 py-2.5 sm:py-3 md:py-3.5 rounded-lg font-bold text-sm sm:text-base md:text-lg hover:bg-white/20 transition">
                            {{ $guestSite['guest_hero_secondary_cta_label'] }}
                        </a>
                    </div>
                </div>
                {{-- Right: Quick Request Widget --}}
                @if($guestSite['guest_show_quick_availability'])
                <div class="flex items-center justify-center mt-8 lg:mt-0">
                    <div class="relative w-full max-w-lg">
                        <div class="rounded-2xl p-4 sm:p-5 md:p-6 lg:p-8 border border-white/25 shadow-2xl" style="background: rgba(0, 54, 21, 0.68); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);">
                            <div class="text-center mb-4 md:mb-5 lg:mb-6">
                                <svg class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 text-[#FFC600] mx-auto mb-2 md:mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-white mb-1">Quick Availability Check</h3>
                                <p class="text-xs sm:text-sm md:text-base text-white/75">Check availability for your dates</p>
                            </div>
                            
                            <form action="{{ route('guest.rooms', [], false) }}" method="GET" class="space-y-3 md:space-y-4">
                                {{-- Check-in Date --}}
                                <div>
                                    <label for="check_in" class="block text-xs sm:text-sm font-medium text-white/90 mb-1.5 md:mb-2">Check-in</label>
                                    <input 
                                        type="date" 
                                        id="check_in" 
                                        name="check_in" 
                                        value="{{ $defaultCheckIn }}"
                                        min="{{ date('Y-m-d') }}"
                                        required
                                        class="w-full px-3 py-2.5 sm:px-4 sm:py-3 rounded-lg bg-white/95 border border-white/20 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#FFC600] focus:border-transparent text-sm md:text-base"
                                    >
                                </div>

                                {{-- Check-out Date --}}
                                <div>
                                    <label for="check_out" class="block text-xs sm:text-sm font-medium text-white/90 mb-1.5 md:mb-2">Check-out</label>
                                    <input 
                                        type="date" 
                                        id="check_out" 
                                        name="check_out" 
                                        value="{{ $defaultCheckOut }}"
                                        min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                                        required
                                        class="w-full px-3 py-2.5 sm:px-4 sm:py-3 rounded-lg bg-white/95 border border-white/20 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#FFC600] focus:border-transparent text-sm md:text-base"
                                    >
                                </div>

                                {{-- Guest Count --}}
                                <div>
                                    <label for="guests" class="block text-xs sm:text-sm font-medium text-white/90 mb-1.5 md:mb-2">Guests</label>
                                    <select 
                                        id="guests" 
                                        name="guests"
                                        class="guest-select w-full px-3 py-2.5 sm:px-4 sm:py-3 rounded-lg bg-white/95 border border-white/20 text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#FFC600] focus:border-transparent text-sm md:text-base"
                                    >
                                        <option value="1">1 Guest</option>
                                        <option value="2" selected>2 Guests</option>
                                        <option value="3">3 Guests</option>
                                        <option value="4">4 Guests</option>
                                        <option value="5">5+ Guests</option>
                                    </select>
                                </div>

                                {{-- Submit Button --}}
                                <button 
                                    type="submit"
                                    class="w-full bg-[#FFC600] text-[#00491E] px-4 py-3 sm:px-6 sm:py-3.5 md:py-4 rounded-lg font-bold text-sm sm:text-base md:text-lg hover:bg-yellow-400 transition shadow-lg flex items-center justify-center gap-2"
                                >
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Check Availability
                                </button>
                            </form>

                            <div class="mt-3 md:mt-4 lg:mt-5 flex items-center justify-center text-xs md:text-sm text-white/60">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1 sm:mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                Real-time availability check
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 z-10 h-16 bg-gradient-to-t from-gray-50 to-transparent"></div>
    </section>

    @include('guest.partials.flash-messages', [
        'wrap' => false,
        'containerClass' => 'mx-auto max-w-7xl space-y-3 px-4 py-8 sm:px-6 lg:px-8',
    ])

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
                            <img src="{{ \App\Support\MediaUrl::url($amenity['image']) }}" alt="{{ $amenity['name'] }}" class="h-16 w-16 object-cover rounded mb-2" />
                        @endif
                        <span class="font-semibold text-[#00491E]">{{ $amenity['name'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- Room Types Preview --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
        <div>
            <div class="text-center mb-10 md:mb-12">
                <h2 class="text-2xl md:text-3xl font-bold text-[#00491E] mb-3 md:mb-4">{{ $guestSite['guest_home_rooms_heading'] }}</h2>
                <p class="text-sm md:text-base text-gray-600 max-w-2xl mx-auto">{{ $guestSite['guest_home_rooms_intro'] }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                @forelse($roomTypes as $roomType)
                    <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition group">
                        @if($roomType->images && count($roomType->images))
                            <div class="h-48 bg-gray-200 overflow-hidden">
                                @php
                                    $cardImageSources = app(\App\Services\ResponsiveRoomImageService::class)
                                        ->cardSources(collect($roomType->images)->first());
                                @endphp
                                <picture>
                                    @if($cardImageSources)
                                        <source type="image/webp" srcset="{{ collect($cardImageSources)->map(fn (string $path, int $width): string => \App\Support\MediaUrl::url($path).' '.$width.'w')->implode(', ') }}" sizes="(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw">
                                    @endif
                                    <img src="{{ \App\Support\MediaUrl::url(collect($roomType->images)->first()) }}" alt="{{ $roomType->name }}" width="960" height="576" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                </picture>
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
                                {{--
                                <h3 class="text-xl font-bold text-[#00491E]">{{ $roomType->name }}@if($roomType->has_capacity_variants) — up to {{ $roomType->variant_capacity }} guests@endif</h3>
                                --}}
                                <h3 class="text-xl font-bold text-[#00491E]">{{ $roomType->name }}{{ $roomType->has_capacity_variants ? ' - up to '.$roomType->variant_capacity.' guests' : '' }}</h3>
                                <span class="bg-[#FFC600] text-[#00491E] px-3 py-1 rounded-full text-sm font-bold">
                                    {{ $roomType->getFormattedPrice() }}
                                </span>
                            </div>
                            <p class="text-gray-600 text-sm mb-3">{{ Str::limit($roomType->description, 100) }}</p>
                            <div class="flex items-center text-sm text-gray-500 mb-4">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Up to {{ $roomType->variant_capacity ?? $roomType->capacity }} {{ Str::plural('guest', $roomType->variant_capacity ?? $roomType->capacity) }}
                                <span class="mx-2">|</span>
                                <span class="text-[#02681E] font-medium">{{ $roomType->availability_label }}</span>
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
                            <a href="{{ route('guest.room-detail', array_merge(['roomType' => $roomType], $roomType->has_capacity_variants ? ['capacity' => $roomType->variant_capacity] : []), false) }}" class="text-[#02681E] font-semibold hover:text-[#00491E] transition text-sm">
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
        </div>
    </section>

    @if($guestSite['guest_show_stay_guide'])
    <section class="py-12 md:py-16 bg-gradient-to-b from-white to-[#00491E]/5 border-y border-[#00491E]/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 md:mb-10">
                <span class="inline-flex items-center rounded-full bg-[#FFC600]/20 px-4 py-1 text-xs font-bold uppercase tracking-[0.2em] text-[#00491E]">
                    Stay Guide
                </span>
                <h2 class="text-2xl md:text-3xl font-bold text-[#00491E] mt-3 md:mt-4 mb-2 md:mb-3">{{ $guestSite['guest_stay_guide_heading'] }}</h2>
                <p class="text-sm md:text-base text-gray-600 max-w-3xl mx-auto">
                    {{ $guestSite['guest_stay_guide_intro'] }}
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 md:gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-[#00491E]/10 p-6 md:p-8">
                    <div class="flex items-center gap-3 mb-5 md:mb-6">
                        <div class="w-10 h-10 rounded-full bg-[#00491E]/10 text-[#00491E] flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-[#00491E]">Included in Most Stays</h3>
                            <p class="text-sm text-gray-500">Core essentials guests can usually expect.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1">
                        @foreach($stayInclusions as $item)
                            <div class="border-b border-[#00491E]/10 py-3 text-sm text-[#00491E] font-medium flex items-start gap-3 last:border-b-0 sm:[&:nth-last-child(-n+2)]:border-b-0">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#00491E]/8 text-[#02681E]">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                <div class="bg-[#00491E] rounded-xl shadow-sm border border-[#02681E]/80 p-6 md:p-8 text-white">
                    <div class="flex items-center gap-3 mb-5 md:mb-6">
                        <div class="w-10 h-10 rounded-full bg-[#FFC600]/15 text-[#FFC600] flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold">Available Upon Request</h3>
                            <p class="text-sm text-white/70">Helpful extras that may be arranged in advance.</p>
                        </div>
                    </div>

                    <div class="divide-y divide-white/12">
                        @foreach($optionalAddOns as $item)
                            <div class="py-3 text-sm font-medium flex items-start gap-3">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#FFC600]/12 text-[#FFC600]">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
                                    </svg>
                                </span>
                                <span class="flex-1">
                                    <span class="block">{{ $item->name }}</span>
                                    @if($item->description)
                                        <span class="block mt-1 text-xs font-normal text-white/70">{{ $item->description }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 text-sm font-bold text-[#FFC600]">
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
                <p class="mx-auto max-w-4xl border-t border-[#00491E]/10 pt-4 text-sm text-gray-600">
                    Availability may vary by room type, season, and reservation arrangement. Room detail pages remain the best source for exact inclusions.
                </p>
            </div>
        </div>
    </section>
    @endif

    {{-- Booking Policy & FAQ --}}
    @php
        $bookingPolicy = $guestSite['guest_booking_policy'];
        $faq = $guestSite['guest_faq_items'];
    @endphp
    @if($guestSite['guest_show_booking_policy'] && $bookingPolicy)
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-[#00491E] mb-4">Booking Policy & Terms</h2>
            <p class="text-gray-700 max-w-2xl mx-auto">{{ $bookingPolicy }}</p>
        </div>
    </section>
    @endif
    @if($guestSite['guest_show_faq'] && is_array($faq) && count($faq))
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
    @if($testimonials->isNotEmpty())
    <section class="bg-white py-12 md:py-16" aria-labelledby="guest-testimonials-heading">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 md:mb-10">
                <p class="text-sm font-bold uppercase tracking-wide text-[#02681E]">Verified Stays</p>
                <h2 id="guest-testimonials-heading" class="mt-2 text-2xl md:text-3xl font-bold text-[#00491E]">What Our Guests Say</h2>
                <p class="mt-3 text-sm md:text-base text-gray-600">Guest feedback from verified stays.</p>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($testimonials as $testimonial)
                    <figure class="flex h-full flex-col rounded-xl border border-gray-200 bg-gray-50 p-6 shadow-sm">
                        <div class="flex items-center gap-1 text-[#FFC600]" aria-label="{{ $testimonial->overall_rating }} out of 5 stars">
                            @for($star = 1; $star <= 5; $star++)
                                <span aria-hidden="true" class="text-xl {{ $star <= $testimonial->overall_rating ? '' : 'text-gray-300' }}">&#9733;</span>
                            @endfor
                        </div>
                        <blockquote class="mt-4 flex-1 text-gray-700">&ldquo;{{ $testimonial->comments }}&rdquo;</blockquote>
                        <figcaption class="mt-5 border-t border-gray-200 pt-4">
                            <p class="font-bold text-[#00491E]">{{ $testimonial->publicGuestName() }}</p>
                            @if($roomTypeLabel = $testimonial->publicRoomTypeLabel())
                                <p class="mt-1 text-xs text-gray-500">Stayed in: {{ $roomTypeLabel }}</p>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="bg-[#00491E]/5 py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10 md:mb-12">
                <h2 class="text-2xl md:text-3xl font-bold text-[#00491E] mb-3 md:mb-4">{{ $guestSite['guest_reservation_steps_heading'] }}</h2>
                <p class="text-sm md:text-base text-gray-600">{{ $guestSite['guest_reservation_steps_intro'] }}</p>
                <p class="mt-3 inline-flex items-center rounded-full bg-[#FFC600]/20 px-4 py-2 text-xs md:text-sm font-semibold text-[#00491E]">
                    {{ $guestSite['guest_reservation_processing_time'] }}
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
                @php
                    $icons = [
                        'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
                        'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
                        'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                        'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z',
                    ];
                    $steps = $guestSite['guest_reservation_steps'] ?: \App\Support\GuestSiteSettings::defaults()['guest_reservation_steps'];
                @endphp
                @foreach($steps as $i => $step)
                    <div class="text-center">
                        <div class="w-16 h-16 bg-[#00491E] rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-[#FFC600]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icons[$i] ?? $icons[0] }}"/>
                            </svg>
                        </div>
                        <div class="text-[#FFC600] font-bold text-sm mb-1">Step {{ $i + 1 }}</div>
                        <h3 class="font-bold text-[#00491E] mb-2">{{ $step['title'] }}</h3>
                        <p class="text-gray-600 text-sm">{{ $step['description'] ?? ($step['desc'] ?? '') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script @if(request()->attributes->get('csp_nonce')) nonce="{{ request()->attributes->get('csp_nonce') }}" @endif>
    // Quick Booking date validation
    document.addEventListener('DOMContentLoaded', function() {
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        
        if (!checkInInput || !checkOutInput) return;

        const toDateString = (date) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const addDays = (dateString, days) => {
            const [year, month, day] = dateString.split('-').map(Number);
            const date = new Date(year, month - 1, day);
            date.setDate(date.getDate() + days);
            return toDateString(date);
        };

        const syncCheckoutMinimum = () => {
            if (!checkInInput.value) return;

            const minCheckOut = addDays(checkInInput.value, 1);
            checkOutInput.setAttribute('min', minCheckOut);

            if (!checkOutInput.value || checkOutInput.value <= checkInInput.value) {
                checkOutInput.value = minCheckOut;
            }
        };

        checkInInput.min = checkInInput.min || toDateString(new Date());
        if (!checkInInput.value || checkInInput.value < checkInInput.min) {
            checkInInput.value = checkInInput.min;
        }
        syncCheckoutMinimum();
        
        // Update check-out minimum when check-in changes
        checkInInput.addEventListener('change', syncCheckoutMinimum);
        
        // Validate on form submit
        const bookingForm = checkInInput.closest('form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function(e) {
                const checkIn = new Date(checkInInput.value);
                const checkOut = new Date(checkOutInput.value);
                
                if (checkOut <= checkIn) {
                    e.preventDefault();
                    alert('Check-out date must be after check-in date');
                    return false;
                }
            });
        }
    });
</script>
@endpush
