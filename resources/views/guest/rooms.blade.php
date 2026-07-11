@extends('layouts.guest')

@section('title', 'Room Catalog')

@section('content')
    @php
        $guestDateDefaults = \App\Support\GuestDatePolicy::defaults(
            $checkIn?->format('Y-m-d'),
            $checkOut?->format('Y-m-d')
        );
        $defaultCheckIn = $guestDateDefaults['check_in'];
        $defaultCheckOut = $guestDateDefaults['check_out'];
        $defaultMinCheckIn = $guestDateDefaults['min_check_in'];
        $defaultMinCheckOut = $guestDateDefaults['min_check_out'];
        $priceRangeOptions = [
            '' => 'Any budget',
            '0-799' => 'Under &#8369;800',
            '800-1199' => '&#8369;800 - &#8369;1,199',
            '1200-1699' => '&#8369;1,200 - &#8369;1,699',
            '1700-' => '&#8369;1,700+',
        ];
        $currentPriceRange = match (true) {
            $priceMin === null && $priceMax === null => '',
            (int) ($priceMin ?? 0) === 0 && (int) ($priceMax ?? 0) === 799 => '0-799',
            (int) ($priceMin ?? 0) === 800 && (int) ($priceMax ?? 0) === 1199 => '800-1199',
            (int) ($priceMin ?? 0) === 1200 && (int) ($priceMax ?? 0) === 1699 => '1200-1699',
            (int) ($priceMin ?? 0) === 1700 && $priceMax === null => '1700-',
            default => 'custom',
        };
    @endphp

    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Room Catalog</h1>
            <p class="text-gray-200">Browse our available room types and find the perfect accommodation for your stay.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8 bg-white rounded-xl shadow-md p-5 md:p-6">
            <div class="mb-5 flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="text-base md:text-lg font-bold text-[#00491E] mb-1">Search Rooms</h3>
                    @if($checkIn && $checkOut)
                        <p class="text-xs md:text-sm text-gray-600">
                            Showing availability for <span class="font-semibold text-[#02681E]">{{ $checkIn->format('M d, Y') }} - {{ $checkOut->format('M d, Y') }}</span>
                            @if($guests)
                                <span class="text-gray-400">&bull;</span> {{ $guests }} {{ Str::plural('guest', $guests) }}
                            @endif
                        </p>
                    @else
                        <p class="text-xs md:text-sm text-gray-600">Enter dates to check availability for your trip.</p>
                    @endif
                </div>
            </div>

            <form action="{{ route('guest.rooms', [], false) }}" method="GET" class="space-y-4" id="room-search-form">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3 md:items-end">
                    <div class="min-w-0">
                        <label for="check_in_filter" class="block text-xs font-medium text-gray-700 mb-1">Check-in</label>
                        <input type="date" id="check_in_filter" name="check_in" value="{{ $defaultCheckIn }}" min="{{ $defaultMinCheckIn }}" required class="h-11 w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#02681E] focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="check_out_filter" class="block text-xs font-medium text-gray-700 mb-1">Check-out</label>
                        <input type="date" id="check_out_filter" name="check_out" value="{{ $defaultCheckOut }}" min="{{ $defaultMinCheckOut }}" required class="h-11 w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#02681E] focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="guests_filter" class="block text-xs font-medium text-gray-700 mb-1">Guests</label>
                        <select id="guests_filter" name="guests" class="guest-select h-11 w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#02681E] focus:border-transparent">
                            <option value="">Any</option>
                            @for($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected($guests == $i)>{{ $i }}</option>
                            @endfor
                            <option value="6" @selected($guests >= 6)>5+</option>
                        </select>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <label for="show_unavailable" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[#00491E]">
                        <input type="hidden" name="show_unavailable" value="0">
                        <input type="checkbox" id="show_unavailable" name="show_unavailable" value="1" @checked($showUnavailable) class="h-5 w-5 rounded border-[#00491E]/30 text-[#00491E] focus:ring-[#00491E]">
                        <span>Show unavailable rooms</span>
                    </label>

                    <details class="group rounded-lg border border-[#00491E]/15 bg-[#00491E]/5" @if($hasAdvancedFilters) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-2.5 text-sm font-bold text-[#00491E] transition hover:bg-[#00491E]/10 [&::-webkit-details-marker]:hidden">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12M10 20h4"/>
                                </svg>
                                Advanced Search
                                @if($activeFilterLabels->isNotEmpty())
                                    <span class="rounded-full bg-[#00491E] px-2 py-0.5 text-xs font-bold text-white">{{ $activeFilterLabels->count() }}</span>
                                @endif
                            </span>
                            <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>

                        <div class="border-t border-[#00491E]/10 p-4">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label for="room_sharing_type_filter" class="block text-xs font-medium text-gray-700 mb-1">Room setup</label>
                                    <select id="room_sharing_type_filter" name="room_sharing_type" class="h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-[#02681E]">
                                        <option value="">Any setup</option>
                                        <option value="private" @selected($roomSharingType === 'private')>Private room</option>
                                        <option value="public" @selected($roomSharingType === 'public')>Shared / dormitory</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="pricing_type_filter" class="block text-xs font-medium text-gray-700 mb-1">Pricing type</label>
                                    <select id="pricing_type_filter" name="pricing_type" class="h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-[#02681E]">
                                        <option value="">Any pricing</option>
                                        <option value="flat_rate" @selected($pricingType === 'flat_rate')>Per room / night</option>
                                        <option value="per_person" @selected($pricingType === 'per_person')>Per person / night</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="price_range_filter" class="block text-xs font-medium text-gray-700 mb-1">Budget</label>
                                    <select id="price_range_filter" class="h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-[#02681E]">
                                        @foreach($priceRangeOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($currentPriceRange === $value)>{!! $label !!}</option>
                                        @endforeach
                                        @if($currentPriceRange === 'custom')
                                            <option value="custom" selected>Custom budget</option>
                                        @endif
                                    </select>
                                    <input type="hidden" name="price_min" id="price_min_filter" value="{{ $priceMin !== null ? (int) $priceMin : '' }}">
                                    <input type="hidden" name="price_max" id="price_max_filter" value="{{ $priceMax !== null ? (int) $priceMax : '' }}">
                                </div>
                                <div>
                                    <label for="sort_filter" class="block text-xs font-medium text-gray-700 mb-1">Sort by</label>
                                    <select id="sort_filter" name="sort" class="h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-[#02681E]">
                                        <option value="recommended" @selected($sort === 'recommended')>Recommended</option>
                                        <option value="price_low" @selected($sort === 'price_low')>Lowest price</option>
                                        <option value="price_high" @selected($sort === 'price_high')>Highest price</option>
                                        <option value="capacity" @selected($sort === 'capacity')>Largest capacity</option>
                                        <option value="name" @selected($sort === 'name')>Name A-Z</option>
                                    </select>
                                </div>
                            </div>

                            @if($activeAmenities->isNotEmpty())
                                <fieldset class="mt-4">
                                    <legend class="mb-2 text-xs font-medium text-gray-700">Amenities</legend>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($activeAmenities as $amenity)
                                            <label class="cursor-pointer" data-amenity-option>
                                                <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id, $selectedAmenityIds, true)) class="sr-only" data-amenity-checkbox>
                                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition hover:border-[#00491E]/40 focus-visible:ring-2 focus-visible:ring-[#02681E] focus-visible:ring-offset-2" data-amenity-chip>
                                                    {{ $amenity->name }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                        </div>
                    </details>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    @if($checkIn || $checkOut || $guests || $activeFilterLabels->isNotEmpty() || !$showUnavailable)
                        <a href="{{ route('guest.rooms', [], false) }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border-2 border-gray-300 bg-white px-5 text-sm font-bold text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 sm:w-auto">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Clear Filters
                        </a>
                    @endif

                    <button type="submit" class="h-11 w-full bg-[#02681E] text-white px-5 py-2.5 rounded-lg font-bold text-sm hover:bg-[#00491E] hover:shadow-lg active:scale-95 transition-all duration-200 whitespace-nowrap flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Update Search
                    </button>
                </div>
            </form>
        </div>

        @if($activeFilterLabels->isNotEmpty())
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold text-gray-700">Active filters:</span>
                @foreach($activeFilterLabels as $label)
                    <span class="rounded-full bg-[#00491E]/10 px-3 py-1 font-semibold text-[#00491E]">{{ $label }}</span>
                @endforeach
            </div>
        @endif

        <div class="mb-4 flex flex-col gap-2 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
            <p>
                {{ $availableRoomTypesCount }} available {{ Str::plural('room type', $availableRoomTypesCount) }}
                @if($showUnavailable && $unavailableRoomTypesCount > 0)
                    <span class="text-gray-400">&bull;</span> {{ $unavailableRoomTypesCount }} unavailable shown after available rooms
                @elseif(!$showUnavailable)
                    <span class="text-[#02681E] font-semibold">&bull; Showing available rooms only</span>
                @endif
            </p>
            @if(!$showUnavailable && $unavailableRoomTypesCount > 0)
                <a href="{{ route('guest.rooms', array_merge($filterQuery, ['show_unavailable' => 1]), false) }}" class="text-[#02681E] font-semibold hover:text-[#00491E]">
                    Show unavailable rooms
                </a>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            @forelse($roomTypes as $roomType)
                @php
                    $isPrivate = $roomType->isPrivate();
                    $availableCount = $isPrivate ? $roomType->available_rooms_count : ($roomType->available_beds_count ?? 0);
                    $totalCount = $isPrivate ? $roomType->total_rooms_count : ($roomType->total_beds_count ?? 0);
                    $isAvailable = (bool) ($roomType->can_accommodate_requested_guests ?? false);
                    $roomDetailUrl = route('guest.room-detail', array_merge(['roomType' => $roomType], $filterQuery), false);
                    $cardClasses = $isAvailable
                        ? 'bg-white hover:shadow-lg cursor-pointer'
                        : 'bg-gray-50 opacity-75 cursor-default';
                @endphp
                <div role="link" tabindex="0" class="block rounded-xl shadow-md overflow-hidden transition group {{ $cardClasses }}"
                     @if($isAvailable)
                         onclick="window.location.href='{{ $roomDetailUrl }}'"
                         onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); window.location.href='{{ $roomDetailUrl }}'; }"
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
                    @if(!$showUnavailable && $unavailableRoomTypesCount > 0)
                        <p class="mb-3 font-semibold text-gray-700">No available rooms match this search.</p>
                        <p class="mb-5 text-sm">Unavailable room types are currently hidden.</p>
                        <a href="{{ route('guest.rooms', array_merge($filterQuery, ['show_unavailable' => 1]), false) }}" class="inline-flex items-center justify-center rounded-lg bg-[#FFC600] px-5 py-2.5 text-sm font-bold text-[#00491E] hover:bg-yellow-400">
                            Show unavailable rooms
                        </a>
                    @else
                        <p>No room types match the selected filters.</p>
                    @endif
                </div>
            @endforelse
        </div>
    </section>
@endsection

@push('scripts')
<script>
    const checkInFilter = document.getElementById('check_in_filter');
    const checkOutFilter = document.getElementById('check_out_filter');
    const priceRangeFilter = document.getElementById('price_range_filter');
    const priceMinFilter = document.getElementById('price_min_filter');
    const priceMaxFilter = document.getElementById('price_max_filter');
    const amenityCheckboxes = document.querySelectorAll('[data-amenity-checkbox]');

    if (checkInFilter && checkOutFilter) {
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
            if (!checkInFilter.value) return;

            const minCheckOutStr = addDays(checkInFilter.value, 1);
            checkOutFilter.min = minCheckOutStr;

            if (!checkOutFilter.value || checkOutFilter.value <= checkInFilter.value) {
                checkOutFilter.value = minCheckOutStr;
            }
        };

        if (!checkInFilter.value || checkInFilter.value < checkInFilter.min) {
            checkInFilter.value = checkInFilter.min;
        }
        syncCheckoutMinimum();

        checkInFilter.addEventListener('change', syncCheckoutMinimum);

        checkOutFilter.addEventListener('change', function() {
            if (this.value <= checkInFilter.value) {
                alert('Check-out date must be after check-in date.');
                this.value = checkOutFilter.min;
            }
        });
    }

    if (priceRangeFilter && priceMinFilter && priceMaxFilter) {
        priceRangeFilter.addEventListener('change', function() {
            const [min, max] = this.value.split('-');
            priceMinFilter.value = min || '';
            priceMaxFilter.value = max || '';
        });
    }

    amenityCheckboxes.forEach((checkbox) => {
        const chip = checkbox.closest('[data-amenity-option]')?.querySelector('[data-amenity-chip]');
        if (!chip) return;

        const selectedClasses = ['border-[#00491E]', 'bg-[#00491E]', 'text-white'];
        const unselectedClasses = ['border-[#00491E]/15', 'bg-white', 'text-[#00491E]'];

        const syncAmenityChip = () => {
            chip.classList.remove(...selectedClasses, ...unselectedClasses);
            chip.classList.add(...(checkbox.checked ? selectedClasses : unselectedClasses));
        };

        checkbox.addEventListener('change', syncAmenityChip);
        syncAmenityChip();
    });
</script>
@endpush
