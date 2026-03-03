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
                    Have a question about your reservation or just want to get in touch? We're here to help.
                </p>
            </div>
        </div>
    </section>

    {{-- Tabbed Message Section --}}
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12"
        x-data="{ activeTab: '{{ session('active_tab', $reservation ? 'reservation' : 'reservation') }}' }"
    >
        {{-- Tab Nav --}}
        <div class="flex rounded-xl overflow-hidden border border-gray-200 mb-8 shadow-sm">
            <button
                @click="activeTab = 'reservation'"
                :class="activeTab === 'reservation'
                    ? 'bg-[#00491E] text-white'
                    : 'bg-white text-gray-600 hover:bg-gray-50'"
                class="flex-1 py-4 px-6 text-sm font-semibold transition-colors duration-200 flex items-center justify-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Reservation Messages
            </button>
            <div class="w-px bg-gray-200"></div>
            <button
                @click="activeTab = 'inquiry'"
                :class="activeTab === 'inquiry'
                    ? 'bg-[#00491E] text-white'
                    : 'bg-white text-gray-600 hover:bg-gray-50'"
                class="flex-1 py-4 px-6 text-sm font-semibold transition-colors duration-200 flex items-center justify-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                General Inquiry
            </button>
        </div>

        {{-- ── TAB 1: Reservation Messages ── --}}
        <div x-show="activeTab === 'reservation'" x-transition>

            @if($errors->any() && session('active_tab') !== 'inquiry')
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            @if(!$reservation)
                {{-- Lookup Form --}}
                <div class="bg-white rounded-xl px-10 py-10 mb-8 shadow-sm border border-gray-100">
                    <h2 class="text-2xl font-bold text-[#00491E] mb-2">Find Your Reservation</h2>
                    <p class="text-gray-600 mb-8">Enter your reservation reference number to view and send messages.</p>

                    <form action="{{ route('guest.messages') }}" method="GET" class="space-y-5">
                        <div>
                            <label for="reference" class="block text-sm font-medium text-gray-700 mb-2">Reservation Reference Number</label>
                            <input
                                type="text"
                                name="reference"
                                id="reference"
                                value="{{ $referenceNumber }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent text-base"
                                placeholder="e.g., 2026-0001"
                                required
                            >
                        </div>
                        <button type="submit" class="w-full bg-[#00491E] text-white px-6 py-3 rounded-lg font-bold text-base hover:bg-[#02681E] transition shadow">
                            Find Reservation
                        </button>
                    </form>
                </div>

                @if($referenceNumber)
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <p class="font-medium">Reservation not found</p>
                        <p class="text-sm">Please check your reference number and try again.</p>
                    </div>
                @endif

                <div class="bg-amber-50 border border-amber-200 rounded-lg p-5">
                    <p class="text-sm text-amber-800">
                        <span class="font-semibold">No reference number?</span>
                        Switch to the <button @click="activeTab = 'inquiry'" class="underline font-medium hover:text-amber-900">General Inquiry</button> tab to contact us directly.
                    </p>
                </div>

            @else
                {{-- Reservation Found - Show Thread --}}
                <div class="bg-white rounded-xl p-8 mb-6 shadow-sm border border-gray-100">
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-[#00491E]">Reservation: {{ $reservation->reference_number }}</h2>
                            <p class="text-gray-600 text-sm">Guest: {{ $reservation->guest_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Check-in: {{ \Carbon\Carbon::parse($reservation->check_in_date)->format('M d, Y') }}
                                &nbsp;|&nbsp;
                                Check-out: {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('M d, Y') }}
                            </p>
                        </div>
                        <a href="{{ route('guest.messages') }}" class="text-sm text-[#00491E] hover:underline font-medium flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back to Search
                        </a>
                    </div>

                    {{-- Thread --}}
                    <div class="space-y-4 mb-8 max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4 bg-gray-50">
                        @forelse($messages as $msg)
                            <div class="flex {{ $msg->sender_type === 'guest' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-lg {{ $msg->sender_type === 'guest' ? 'bg-[#00491E] text-white' : 'bg-white text-gray-900 border border-gray-200' }} rounded-lg px-4 py-3 shadow-sm">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if($msg->sender_type === 'guest')
                                            <span class="text-xs font-semibold text-[#FFC600]">You</span>
                                        @else
                                            <span class="text-xs font-semibold text-[#00491E]">Staff</span>
                                        @endif
                                        <span class="text-xs {{ $msg->sender_type === 'guest' ? 'text-gray-300' : 'text-gray-400' }}">
                                            {{ $msg->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                    <p class="text-sm whitespace-pre-wrap">{{ $msg->message }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-gray-500 py-8">
                                <p>No messages yet. Send a message below to start the conversation.</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Reply Form --}}
                    <form action="{{ route('guest.messages.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="reference_number" value="{{ $reservation->reference_number }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Your Name</label>
                                <input type="text" name="sender_name"
                                    value="{{ old('sender_name', $reservation->guest_name) }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                                    required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Your Email</label>
                                <input type="email" name="sender_email"
                                    value="{{ old('sender_email', $reservation->guest_email) }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                                    required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Your Message</label>
                            <textarea name="message" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                                placeholder="Type your message here..."
                                required>{{ old('message') }}</textarea>
                        </div>

                        <button type="submit" class="w-full bg-[#00491E] text-white px-6 py-3 rounded-lg font-bold hover:bg-[#02681E] transition shadow">
                            Send Message
                        </button>
                    </form>
                </div>
            @endif
        </div>

        {{-- ── TAB 2: General Inquiry ── --}}
        <div x-show="activeTab === 'inquiry'" x-transition>

            @if($errors->any() && session('active_tab') === 'inquiry')
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('inquiry_success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-4 rounded-lg mb-6 flex items-start gap-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-semibold">Inquiry submitted!</p>
                        <p class="text-sm">{{ session('inquiry_success') }}</p>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-[#00491E] mb-1">Send Us a Message</h2>
                    <p class="text-gray-600 text-sm">No reservation needed. Fill in the form below and our staff will get back to you via email.</p>
                </div>

                <form action="{{ route('guest.contact.store') }}" method="POST" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Your Name <span class="text-red-500">*</span></label>
                            <input type="text" name="sender_name"
                                value="{{ old('sender_name') }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                                placeholder="Juan dela Cruz"
                                required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" name="sender_email"
                                value="{{ old('sender_email') }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                                placeholder="you@example.com"
                                required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subject <span class="text-red-500">*</span></label>
                        <input type="text" name="subject"
                            value="{{ old('subject') }}"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                            placeholder="e.g., Parking availability, Room facilities, Rates..."
                            maxlength="150"
                            required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Message <span class="text-red-500">*</span></label>
                        <textarea name="message" rows="5"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#00491E] focus:border-transparent"
                            placeholder="Write your inquiry here..."
                            maxlength="5000"
                            required>{{ old('message') }}</textarea>
                        <p class="text-xs text-gray-400 mt-1">Max 5,000 characters.</p>
                    </div>

                    <button type="submit"
                        class="w-full bg-[#FFC600] text-[#00491E] px-6 py-3 rounded-lg font-bold hover:bg-[#00491E] hover:text-[#FFC600] transition-all duration-200 shadow">
                        Submit Inquiry
                    </button>
                </form>
            </div>

            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-5">
                <h3 class="text-sm font-bold text-blue-900 mb-1">What happens next?</h3>
                <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
                    <li>Our staff will review your inquiry</li>
                    <li>We'll reply to your email address within 1–2 business days</li>
                    <li>For urgent matters, please call us directly</li>
                </ul>
            </div>
        </div>
    </section>
@endsection
