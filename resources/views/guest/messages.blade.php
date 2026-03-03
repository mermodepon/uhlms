@extends('layouts.guest')

@section('title', 'Contact Us')

@section('content')
    {{-- Header Section --}}
    <section class="bg-gradient-to-br from-[#00491E] via-[#02681E] to-[#00491E] text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">
                    Message <span class="text-[#FFC600]">Center</span>
                </h1>
                <p class="text-xl text-gray-200 max-w-2xl mx-auto">
                    Have questions about your reservation? Send us a message and our staff will respond shortly.
                </p>
            </div>
        </div>
    </section>

    {{-- Message Section --}}
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Lookup Form (if no reservation selected) --}}
        @if(!$reservation)
            <div class="bg-white rounded-xl px-16 py-12 mb-8">
                <h2 class="text-3xl font-bold text-[#00491E] mb-4">Find Your Reservation</h2>
                <p class="text-gray-600 text-lg mb-10">Enter your reservation reference number to view and send messages.</p>
                
                <form action="{{ route('guest.messages') }}" method="GET" class="space-y-6">
                    <div>
                        <label for="reference" class="block text-sm font-medium text-gray-700 mb-3">Reservation Reference Number</label>
                        <input 
                            type="text" 
                            name="reference" 
                            id="reference" 
                            value="{{ $referenceNumber }}"
                            class="w-full px-5 py-4 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                            placeholder="e.g., 2026-0001"
                            required
                        >
                    </div>
                    <button type="submit" class="w-full bg-[#00491E] text-white px-6 py-4 rounded-lg font-bold text-lg hover:bg-[#02681E] transition shadow-lg">
                        Find Reservation
                    </button>
                </form>
            </div>

            @if($referenceNumber)
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <p class="font-medium">Reservation not found</p>
                    <p class="text-sm">Please check your reference number and try again.</p>
                </div>
            @endif
        @else
            {{-- Reservation Found - Show Messages --}}
            <div class="bg-white rounded-xl p-8 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-[#00491E]">Reservation: {{ $reservation->reference_number }}</h2>
                        <p class="text-gray-600">Guest: {{ $reservation->guest_name }}</p>
                        <p class="text-sm text-gray-500">Check-in: {{ \Carbon\Carbon::parse($reservation->check_in_date)->format('M d, Y') }} | Check-out: {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('M d, Y') }}</p>
                    </div>
                    <a href="{{ route('guest.messages') }}" class="text-[#00491E] hover:text-[#02681E] font-medium">
                        ← Back to Search
                    </a>
                </div>

                {{-- Messages Thread --}}
                <div class="space-y-4 mb-8 max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4">
                    @forelse($messages as $message)
                        <div class="flex {{ $message->sender_type === 'guest' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-lg {{ $message->sender_type === 'guest' ? 'bg-[#00491E] text-white' : 'bg-gray-100 text-gray-900' }} rounded-lg px-4 py-3">
                                <div class="flex items-center gap-2 mb-1">
                                    @if($message->sender_type === 'guest')
                                        <span class="text-xs font-semibold text-[#FFC600]">You</span>
                                    @else
                                        <span class="text-xs font-semibold text-[#00491E]">{{ ucfirst($message->sender_type) }}</span>
                                    @endif
                                    <span class="text-xs {{ $message->sender_type === 'guest' ? 'text-gray-300' : 'text-gray-500' }}">
                                        {{ $message->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <p class="text-sm whitespace-pre-wrap">{{ $message->message }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-8">
                            <p>No messages yet. Send a message below to start the conversation.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Send Message Form --}}
                <form action="{{ route('guest.messages.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="reference_number" value="{{ $reservation->reference_number }}">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="sender_name" class="block text-sm font-medium text-gray-700 mb-2">Your Name</label>
                            <input 
                                type="text" 
                                name="sender_name" 
                                id="sender_name" 
                                value="{{ old('sender_name', $reservation->guest_name) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                                required
                            >
                        </div>
                        <div>
                            <label for="sender_email" class="block text-sm font-medium text-gray-700 mb-2">Your Email</label>
                            <input 
                                type="email" 
                                name="sender_email" 
                                id="sender_email" 
                                value="{{ old('sender_email', $reservation->guest_email) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                                required
                            >
                        </div>
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Your Message</label>
                        <textarea 
                            name="message" 
                            id="message" 
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                            placeholder="Type your message here..."
                            required
                        >{{ old('message') }}</textarea>
                    </div>

                    <button type="submit" class="w-full bg-[#00491E] text-white px-6 py-3 rounded-lg font-bold hover:bg-[#02681E] transition shadow-lg">
                        Send Message
                    </button>
                </form>
            </div>
        @endif

        {{-- Instructions --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-bold text-blue-900 mb-2">💡 How It Works</h3>
            <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
                <li>Enter your reservation reference number</li>
                <li>View the conversation history with our staff</li>
                <li>Send new messages about your reservation</li>
                <li>Our staff will respond as soon as possible</li>
            </ul>
        </div>
    </section>
@endsection
