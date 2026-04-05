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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            @forelse($roomTypes as $roomType)
                <div role="link" tabindex="0" class="block cursor-pointer bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition group" onclick="window.location.href='{{ route('guest.room-detail', $roomType) }}'" onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); window.location.href='{{ route('guest.room-detail', $roomType) }}'; }">
                    <div class="md:flex">
                        @if($roomType->images && count($roomType->images))
                            <div class="md:w-1/3 h-48 md:h-auto bg-gray-200">
                                <img src="{{ asset('storage/' . collect($roomType->images)->first()) }}" alt="{{ $roomType->name }}" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="md:w-1/3 h-48 md:h-auto bg-gradient-to-br from-[#00491E] to-[#02681E] flex items-center justify-center">
                                <svg class="w-12 h-12 text-[#FFC600]/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="p-6 md:w-2/3">
                            <div class="flex justify-between items-start mb-3">
                                <h2 class="text-xl font-bold text-[#00491E]">{{ $roomType->name }}</h2>
                                <span class="bg-[#FFC600] text-[#00491E] px-3 py-1 rounded-full text-sm font-bold whitespace-nowrap">
                                    {{ $roomType->getFormattedPrice() }}
                                </span>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">{{ $roomType->description }}</p>

                            <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Capacity: {{ $roomType->capacity }}
                                </span>
                                <span class="flex items-center text-[#02681E] font-medium">
                                    {{ $roomType->available_rooms_count }} of {{ $roomType->rooms_count }} available
                                </span>
                            </div>

                            @if($roomType->amenities->count())
                                <div class="flex flex-wrap gap-1 mb-4">
                                    @foreach($roomType->amenities as $amenity)
                                        <span class="bg-[#00491E]/10 text-[#00491E] px-2 py-0.5 rounded text-xs">{{ $amenity->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex gap-3">
                                <span class="relative z-10 text-[#02681E] font-semibold group-hover:text-[#00491E] transition text-sm">
                                    View Details &rarr;
                                </span>
                                @if($roomType->virtual_tour_url)
                                    <span class="text-gray-300">|</span>
                                    <button type="button" class="relative z-10 text-[#919F02] font-semibold hover:text-[#02681E] transition text-sm bg-transparent p-0 flex items-center gap-1" onclick="event.stopPropagation(); window.location.href='{{ route('guest.room-detail', $roomType) }}#virtual-tour'">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> Virtual Tour
                                    </button>
                                @endif
                            </div>
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
