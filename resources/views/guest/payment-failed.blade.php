@extends('layouts.guest')

@section('title', 'Payment Failed')

@section('content')
    <section class="bg-gradient-to-r from-red-600 to-red-700 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Payment Failed</h1>
            <p class="text-gray-100">There was an issue processing your payment</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-xl shadow-md p-8 text-center">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>

            <h2 class="text-2xl font-bold text-gray-900 mb-3">Payment Could Not Be Completed</h2>
            <p class="text-gray-600 mb-6">
                Unfortunately, we were unable to process your payment. This could be due to insufficient funds, cancelled transaction, or network issues.
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
                    </div>
                </div>
            @endif

            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 text-left">
                <h4 class="font-bold text-yellow-900 mb-2 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    What to Do Next
                </h4>
                <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside ml-7">
                    <li>Check your account balance or payment method</li>
                    <li>Try again using a different payment method</li>
                    <li>Contact our staff if the problem persists</li>
                    <li>Your reservation is still active and awaiting payment</li>
                </ul>
            </div>

            <div class="space-y-3">
                @if($reservation && $reservation->isPaymentLinkValid())
                    <a href="{{ $reservation->generatePaymentLink(false) }}" class="inline-block w-full sm:w-auto bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-3 px-8 rounded-lg font-bold hover:from-[#003817] hover:to-[#015717] transition-all">
                        Try Again
                    </a>
                @endif
                <a href="{{ route('guest.home', [], false) }}" class="inline-block w-full sm:w-auto bg-gray-200 text-gray-800 py-3 px-8 rounded-lg font-bold hover:bg-gray-300 transition-all {{ $reservation && $reservation->isPaymentLinkValid() ? 'ml-0 sm:ml-3' : '' }}">
                    Return to Homepage
                </a>
            </div>

            <div class="mt-8 text-sm text-gray-600">
                <p>Need help? Contact us at:</p>
                <p class="font-medium text-gray-900 mt-1">Email: support@uhlms.edu.ph | Phone: (123) 456-7890</p>
            </div>
        </div>
    </section>
@endsection
