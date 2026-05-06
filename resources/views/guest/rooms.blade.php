@extends('layouts.guest')

@section('title', 'Room Catalog')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Room Catalog</h1>
            <p class="text-gray-200">Browse our available room types and find the perfect accommodation for your stay.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {{-- Virtual Tour Banner --}}
        <div class="mb-8 bg-gradient-to-r from-[#00491E] to-[#02681E] rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-[#FFC600] rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-[#00491E]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <div>
                    <p class="text-white font-bold">Want to look around first?</p>
                    <p class="text-gray-200 text-sm">Take an interactive 360° virtual tour of the establishment before choosing a room.</p>
                </div>
            </div>
            <a href="{{ route('guest.tour.viewer', [], false) }}" class="whitespace-nowrap bg-[#FFC600] text-[#00491E] px-5 py-2 rounded-lg font-bold text-sm hover:bg-yellow-400 transition flex-shrink-0">
                Take the Tour &rarr;
            </a>
        </div>

        {{-- Date Filter Widget --}}
        <div class="mb-8 bg-white rounded-xl shadow-md p-5 md:p-6">
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="flex-shrink-0">
                    <h3 class="text-base md:text-lg font-bold text-[#00491E] mb-1">Check Availability</h3>
                    @if($checkIn && $checkOut)
                        <p class="text-xs md:text-sm text-gray-600">
                            Showing availability for <span class="font-semibold text-[#02681E]">{{ $checkIn->format('M d, Y') }} - {{ $checkOut->format('M d, Y') }}</span>
                            @if($guests)
                                • {{ $guests }} {{ Str::plural('guest', $guests) }}
                            @endif
                        </p>
                    @else
                        <p class="text-xs md:text-sm text-gray-600">Enter dates to check availability for your trip</p>
                    @endif
                </div>
                <div class="flex-1">
                    <form action="{{ route('guest.rooms', [], false) }}" method="GET" class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <label for="check_in_filter" class="block text-xs font-medium text-gray-700 mb-1">Check-in</label>
                            <input type="date" id="check_in_filter" name="check_in" value="{{ $checkIn?->format('Y-m-d') }}" min="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#02681E] focus:border-transparent">
                        </div>
                        <div class="flex-1">
                            <label for="check_out_filter" class="block text-xs font-medium text-gray-700 mb-1">Check-out</label>
                            <input type="date" id="check_out_filter" name="check_out" value="{{ $checkOut?->format('Y-m-d') }}" min="{{ date('Y-m-d', strtotime('+1 day')) }}" required class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#02681E] focus:border-transparent">
                        </div>
                        <div class="flex-shrink-0 sm:w-32">
                            <label for="guests_filter" class="block text-xs font-medium text-gray-700 mb-1">Guests</label>
                            <select id="guests_filter" name="guests" class="guest-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#02681E] focus:border-transparent">
                                <option value="">Any</option>
                                @for($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}" @selected($guests == $i)>{{ $i }}</option>
                                @endfor
                                <option value="6" @selected($guests >= 6)>5+</option>
                            </select>
                        </div>
                        <div class="flex gap-2 sm:flex-col sm:justify-end sm:pt-5">
                            <button type="submit" class="flex-1 sm:flex-none bg-[#02681E] text-white px-5 py-2.5 rounded-lg font-bold text-sm hover:bg-[#00491E] hover:shadow-lg active:scale-95 transition-all duration-200 whitespace-nowrap flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Update Search
                            </button>
                            @if($checkIn || $checkOut)
                                <a href="{{ route('guest.rooms', [], false) }}" class="flex-1 sm:flex-none bg-white border-2 border-gray-300 text-gray-700 px-5 py-2.5 rounded-lg font-bold text-sm hover:border-gray-400 hover:bg-gray-50 hover:shadow-md active:scale-95 transition-all duration-200 text-center whitespace-nowrap flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Clear Filters
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            @forelse($roomTypes as $roomType)
                @php
                    $isPrivate = $roomType->isPrivate();
                    $availableCount = $isPrivate ? $roomType->available_rooms_count : ($roomType->available_beds_count ?? 0);
                    $totalCount = $isPrivate ? $roomType->total_rooms_count : ($roomType->total_beds_count ?? 0);
                    $isAvailable = (bool) ($roomType->can_accommodate_requested_guests ?? false);
                    $cardClasses = $isAvailable 
                        ? 'bg-white hover:shadow-lg cursor-pointer' 
                        : 'bg-gray-50 opacity-75 cursor-default';
                @endphp
                <div role="link" tabindex="0" class="block rounded-xl shadow-md overflow-hidden transition group {{ $cardClasses }}" 
                     @if($isAvailable)
                         onclick="window.location.href='{{ route('guest.room-detail', $roomType, false) }}'" 
                         onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); window.location.href='{{ route('guest.room-detail', $roomType, false) }}'; }"
                     @endif>
                    <div class="md:flex">
                        @if($roomType->images && count($roomType->images))
                            <div class="md:w-1/3 h-48 md:h-auto bg-gray-200 relative">
                                <img src="{{ \App\Support\MediaUrl::url(collect($roomType->images)->first()) }}" alt="{{ $roomType->name }}" class="w-full h-full object-cover {{ !$isAvailable ? 'grayscale' : '' }}">
                                @if(!$isAvailable)
                                    <div class="absolute inset-0 bg-gray-900/40 flex items-center justify-center">
                                        <span class="bg-white/90 text-gray-900 px-3 py-1 rounded-lg font-bold text-sm">Unavailable</span>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="md:w-1/3 h-48 md:h-auto bg-gradient-to-br from-[#00491E] to-[#02681E] flex items-center justify-center relative">
                                <svg class="w-12 h-12 text-[#FFC600]/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                                </svg>
                                @if(!$isAvailable)
                                    <div class="absolute inset-0 bg-gray-900/40 flex items-center justify-center">
                                        <span class="bg-white/90 text-gray-900 px-3 py-1 rounded-lg font-bold text-sm">Unavailable</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                        <div class="p-6 md:w-2/3">
                            <div class="flex justify-between items-start mb-3">
                                <h2 class="text-xl font-bold {{ $isAvailable ? 'text-[#00491E]' : 'text-gray-500' }}">{{ $roomType->name }}</h2>
                                <span class="bg-[#FFC600] text-[#00491E] px-3 py-1 rounded-full text-sm font-bold whitespace-nowrap">
                                    {{ $roomType->getFormattedPrice() }}
                                </span>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">{{ $roomType->description }}</p>

                            <div class="flex items-center gap-4 text-sm mb-4">
                                <span class="flex items-center text-gray-500">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Capacity: {{ $roomType->capacity }}
                                </span>
                                <span class="flex items-center font-medium {{ $isAvailable ? 'text-[#02681E]' : 'text-gray-500' }}">
                                    {{ $availableCount }} of {{ $totalCount }} {{ $isPrivate ? 'rooms' : 'beds' }} available
                                    @if($checkIn && $checkOut && !$isAvailable)
                                        <svg class="w-4 h-4 ml-1 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </span>
                            </div>

                            @if($roomType->amenities->count())
                                <div class="flex flex-wrap gap-1 mb-4">
                                    @foreach($roomType->amenities as $amenity)
                                        <span class="bg-[#00491E]/10 text-[#00491E] px-2 py-0.5 rounded text-xs">{{ $amenity->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($isAvailable)
                                <div class="flex gap-3">
                                    <span class="relative z-10 text-[#02681E] font-semibold group-hover:text-[#00491E] transition text-sm">
                                        View Details &rarr;
                                    </span>
                                </div>
                            @else
                                <div class="text-gray-500 text-sm">
                                    @if($checkIn && $checkOut && $guests && $availableCount > 0)
                                        Not enough {{ $isPrivate ? 'rooms' : 'beds' }} for {{ $guests }} {{ Str::plural('guest', $guests) }}
                                    @elseif($checkIn && $checkOut)
                                        Not available for selected dates
                                    @else
                                        Currently unavailable
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12 text-gray-500">
                    <p>No room types available at the moment.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection

@push('scripts')
<script>
    // Date validation for filter form
    const checkInFilter = document.getElementById('check_in_filter');
    const checkOutFilter = document.getElementById('check_out_filter');

    if (checkInFilter && checkOutFilter) {
        checkInFilter.addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            const minCheckOut = new Date(checkInDate);
            minCheckOut.setDate(minCheckOut.getDate() + 1);
            
            const minCheckOutStr = minCheckOut.toISOString().split('T')[0];
            checkOutFilter.min = minCheckOutStr;
            
            // Update check-out if it's before the new minimum
            if (checkOutFilter.value && new Date(checkOutFilter.value) <= checkInDate) {
                checkOutFilter.value = minCheckOutStr;
            }
        });

        checkOutFilter.addEventListener('change', function() {
            const checkOutDate = new Date(this.value);
            const checkInDate = new Date(checkInFilter.value);
            
            if (checkOutDate <= checkInDate) {
                alert('Check-out date must be after check-in date.');
                this.value = '';
            }
        });
    }
</script>
@endpush
