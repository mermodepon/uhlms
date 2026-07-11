@extends('layouts.guest')

@php
    $guestSite = \App\Support\GuestSiteSettings::all();
    $aboutParagraphs = collect(preg_split("/\r\n|\n|\r/", (string) $guestSite['guest_about_body']))
        ->map(fn ($paragraph) => trim($paragraph))
        ->filter()
        ->values();
@endphp

@section('title', $guestSite['guest_nav_about_label'] ?? 'About Us')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <span class="inline-flex items-center rounded-full bg-[#FFC600]/20 px-4 py-1 text-xs font-bold uppercase tracking-[0.2em] text-[#FFC600]">
                {{ $guestSite['guest_nav_about_label'] ?? 'About Us' }}
            </span>
            <h1 class="mt-4 text-3xl md:text-4xl font-bold">{{ $guestSite['guest_about_heading'] }}</h1>
            <p class="mt-3 max-w-3xl text-gray-200">{{ $guestSite['guest_about_intro'] }}</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem] gap-8 items-start">
            <article class="bg-white rounded-xl shadow-md p-6 md:p-8">
                <h2 class="text-2xl font-bold text-[#00491E] mb-5">{{ $guestSite['guest_about_heading'] }}</h2>

                <div class="space-y-4 text-gray-700 leading-relaxed">
                    @forelse($aboutParagraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @empty
                        <p>{{ \App\Support\GuestSiteSettings::defaults()['guest_about_body'] }}</p>
                    @endforelse
                </div>
            </article>

            <aside class="bg-[#00491E]/5 border border-[#00491E]/10 rounded-xl p-6">
                <h3 class="text-lg font-bold text-[#00491E] mb-3">Plan Your Stay</h3>
                <p class="text-sm text-gray-700 mb-5">
                    Browse rooms, check availability, or start the virtual tour before submitting a reservation request.
                </p>
                <div class="space-y-3">
                    <a href="{{ route('guest.rooms', [], false) }}" class="flex items-center justify-center rounded-lg bg-white border border-[#00491E]/15 px-4 py-3 text-sm font-bold text-[#00491E] hover:bg-[#00491E]/5 transition">
                        Browse Rooms
                    </a>
                    <a href="{{ route('guest.tour.viewer', [], false) }}" class="flex items-center justify-center gap-2 rounded-lg bg-[#FFC600] px-4 py-3 text-sm font-bold text-[#00491E] hover:bg-yellow-400 transition">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        Virtual Tour
                    </a>
                    <a href="{{ route('guest.reserve', [], false) }}" class="flex items-center justify-center rounded-lg bg-[#00491E] px-4 py-3 text-sm font-bold text-white hover:bg-[#02681E] transition">
                        {{ $guestSite['guest_nav_reserve_label'] }}
                    </a>
                </div>
            </aside>
        </div>
    </section>
@endsection
