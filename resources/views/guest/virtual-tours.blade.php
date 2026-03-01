@extends('layouts.guest')

@section('title', 'Virtual Tours')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">🎯 360° Virtual Tours</h1>
            <p class="text-gray-200">Explore our rooms in immersive 360° before you book. Click and drag to look around.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @forelse($roomTypes as $roomType)
            <div class="mb-12 bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-6 border-b bg-gray-50">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold text-[#00491E]">{{ $roomType->name }}</h2>
                            <p class="text-gray-600 mt-1">{{ Str::limit($roomType->description, 150) }}</p>
                        </div>
                        <a href="{{ route('guest.room-detail', $roomType) }}"
                           class="bg-[#00491E] text-white px-4 py-2 rounded-lg hover:bg-[#02681E] transition text-sm font-medium whitespace-nowrap">
                            View Details
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <div class="rounded-lg overflow-hidden" style="position: relative; padding-bottom: 50%; height: 0;">
                        <iframe
                            src="{{ $roomType->virtual_tour_url }}"
                            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                            allowfullscreen
                            loading="lazy"
                        ></iframe>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-16 text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                </svg>
                <h3 class="text-xl font-semibold mb-2">No Virtual Tours Available</h3>
                <p>Virtual tours will be added soon. Check back later!</p>
                <a href="{{ route('guest.rooms') }}" class="inline-block mt-4 bg-[#00491E] text-white px-6 py-2 rounded-lg hover:bg-[#02681E] transition">
                    Browse Room Catalog
                </a>
            </div>
        @endforelse
    </section>
@endsection
