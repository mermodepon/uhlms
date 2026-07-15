@extends('layouts.guest')

@section('title', $reservation ? 'Payment Successful' : 'Payment Status')

@section('page-header')
    <section class="bg-gradient-to-r {{ $reservation ? 'from-green-600 to-green-700' : 'from-[#00491E] to-[#02681E]' }} text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">{{ $reservation ? 'Payment Successful!' : 'Payment Status' }}</h1>
            <p class="text-gray-100">{{ $reservation ? 'Your payment return was received' : 'Reservation details are protected' }}</p>
        </div>
    </section>
@endsection

@section('content')
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {{-- Success Message --}}
        <div class="bg-white rounded-xl shadow-md p-8 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h2 class="text-2xl font-bold text-gray-900 mb-3">{{ $reservation ? 'Payment Confirmed' : 'Check Your Reservation Securely' }}</h2>

            @if($reservation)
                @if($message)
                    <p class="text-gray-600 mb-6">{{ $message }}</p>
                @else
                    <p class="text-gray-600 mb-6">
                        Thank you! Your payment has been successfully processed. Your approved reservation has been updated, and staff will send any remaining arrival details shortly.
                    </p>
                @endif
            @else
                <p class="text-gray-600 mb-6">
                    This link cannot display reservation or payment details. Use the secure tracking form or contact support if you need assistance.
                </p>
            @endif

            @if($reservation)
                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                    <h3 class="font-bold text-lg text-gray-900 mb-3">Reservation Details</h3>
                    <div class="text-left space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Reservation Number:</span>
                            <span class="font-bold text-gray-900">{{ $reservation->reference_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Guest Name:</span>
                            <span class="text-gray-900">{{ $reservation->guest_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Check-in:</span>
                            <span class="text-gray-900">{{ $reservation->check_in_date->format('F j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Check-out:</span>
                            <span class="text-gray-900">{{ $reservation->check_out_date->format('F j, Y') }}</span>
                        </div>
                    </div>
                </div>
            @endif

            @if($reservation)
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 text-left">
                <h4 class="font-bold text-blue-900 mb-2 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    Next Steps
                </h4>
                <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside ml-7">
                    <li>You will receive a status update email shortly</li>
                    <li>Our staff will finalize any remaining reservation details</li>
                    <li>The remaining balance is payable upon check-in</li>
                    <li>Please bring a valid ID when you arrive</li>
                </ul>
            </div>

            <div class="space-y-3">
                @if($accountReservationUrl)
                    <a href="{{ $accountReservationUrl }}" class="inline-block w-full sm:w-auto bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-3 px-8 rounded-lg font-bold hover:from-[#003817] hover:to-[#015717] transition-all">
                        Return to Reservation
                    </a>
                @else
                    <a href="{{ route('guest.home', [], false) }}" class="inline-block w-full sm:w-auto bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-3 px-8 rounded-lg font-bold hover:from-[#003817] hover:to-[#015717] transition-all">
                        Return to Homepage
                    </a>
                @endif
                @if($reservation && $trackingUrl)
                    <a href="{{ $trackingUrl }}" class="inline-block w-full sm:w-auto bg-gray-200 text-gray-800 py-3 px-8 rounded-lg font-bold hover:bg-gray-300 transition-all ml-0 sm:ml-3">
                        Track Reservation
                    </a>
                @elseif(!$reservation)
                    <a href="{{ route('guest.track', [], false) }}" class="inline-block w-full sm:w-auto bg-gray-200 text-gray-800 py-3 px-8 rounded-lg font-bold hover:bg-gray-300 transition-all ml-0 sm:ml-3">
                        Track Reservation
                    </a>
                    <a href="{{ route('guest.support', [], false) }}" class="inline-block w-full sm:w-auto text-[#00491E] py-3 px-5 font-bold hover:underline">
                        Contact Support
                    </a>
                @endif
            </div>
            @endif
        </div>
    </section>
@endsection
