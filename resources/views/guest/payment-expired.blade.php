@extends('layouts.guest')

@section('title', 'Payment Link Expired')

@section('content')
    <section class="bg-gradient-to-r from-gray-600 to-gray-700 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Payment Link Expired</h1>
            <p class="text-gray-100">This payment link is no longer valid</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-xl shadow-md p-8 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <h2 class="text-2xl font-bold text-gray-900 mb-3">This Payment Link Has Expired</h2>
            <p class="text-gray-600 mb-6">
                For security reasons, payment links expire after 48 hours. Please contact our staff to receive a new payment link.
            </p>

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
                            <span class="text-gray-600">Status:</span>
                            <span class="px-2 py-1 text-xs rounded-full {{ $reservation->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ ucfirst($reservation->status) }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 text-left">
                <h4 class="font-bold text-blue-900 mb-2 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    To Get a New Payment Link
                </h4>
                <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside ml-7">
                    <li>Contact our homestay staff via email or phone</li>
                    <li>Provide your reservation number: <strong>{{ $reservation->reference_number }}</strong></li>
                    <li>A new secure payment link will be sent to you</li>
                </ul>
            </div>

            <div class="space-y-3">
                <a href="{{ route('guest.home', [], false) }}" class="inline-block w-full sm:w-auto bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-3 px-8 rounded-lg font-bold hover:from-[#003817] hover:to-[#015717] transition-all">
                    Return to Homepage
                </a>
                @if($reservation)
                    <a href="{{ route('guest.track', ['reference' => $reservation->reference_number, 'guest_email' => $reservation->guest_email], false) }}" class="inline-block w-full sm:w-auto bg-gray-200 text-gray-800 py-3 px-8 rounded-lg font-bold hover:bg-gray-300 transition-all ml-0 sm:ml-3">
                        Track Reservation
                    </a>
                @endif
            </div>

            <div class="mt-8 text-sm text-gray-600">
                <p>Contact us:</p>
                <p class="font-medium text-gray-900 mt-1">Email: support@uhlms.edu.ph | Phone: (123) 456-7890</p>
            </div>
        </div>
    </section>
@endsection
