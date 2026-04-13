@extends('layouts.guest')

@section('title', 'Virtual Tour')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="inline-block w-8 h-8 align-middle mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                360° Virtual Tour
            </h1>
            <p class="text-gray-200">Explore our establishment in an immersive guided tour.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-gradient-to-r from-yellow-400 to-[#00491E] rounded-xl shadow-lg overflow-hidden">
            <div class="p-8 text-center">
                <h2 class="text-3xl font-bold text-white mb-4">🎮 Interactive Virtual Tour</h2>
                <p class="text-white text-lg mb-6 max-w-2xl mx-auto">Experience our establishment in an immersive guided tour! Navigate through rooms, explore amenities, and make reservations — all from your browser.</p>
                <a href="{{ route('guest.tour.viewer') }}"
                   class="inline-block bg-white text-[#00491E] font-bold px-8 py-4 rounded-lg hover:bg-gray-100 transition text-lg shadow-lg">
                    🚀 Start Interactive Tour
                </a>
            </div>
        </div>
    </section>
@endsection

