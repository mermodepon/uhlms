@extends('layouts.guest')

@section('title', 'Check-in Payment Status')

@php
    $paid = $payment->gateway_status === 'paid' && $payment->status === 'posted';
    $cancelledOrFailed = $cancelled || in_array($payment->gateway_status, ['cancelled', 'failed'], true);
    $trackingUrl = $reservation->generateGuestTrackingUrl();
@endphp

@section('page-header')
    <section class="bg-gradient-to-r {{ $paid ? 'from-green-600 to-green-700' : ($cancelledOrFailed ? 'from-amber-600 to-amber-700' : 'from-[#00491E] to-[#02681E]') }} py-12 text-white">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold">Check-in Payment</h1>
            <p class="mt-2 text-white/90">Reservation {{ $reservation->reference_number }}</p>
        </div>
    </section>
@endsection

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-xl bg-white p-8 text-center shadow-md">
            @if($paid)
                <h2 class="text-2xl font-bold text-green-700">Payment received</h2>
                <p class="mt-3 text-gray-600">Your check-in balance payment of PHP {{ number_format((float) $payment->amount, 2) }} has been recorded. Staff can complete your check-in without collecting another payment.</p>
            @elseif($cancelledOrFailed)
                <h2 class="text-2xl font-bold text-amber-700">Payment was not completed</h2>
                <p class="mt-3 text-gray-600">No payment has been confirmed. You may return to the reception desk for assistance.</p>
            @else
                <h2 class="text-2xl font-bold text-[#00491E]">Confirming your payment</h2>
                <p class="mt-3 text-gray-600">Your payment return was received. Confirmation from the payment provider may take a moment; staff will be notified automatically.</p>
            @endif

            <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                <p><span class="font-semibold">Reservation:</span> {{ $reservation->reference_number }}</p>
                <p class="mt-1"><span class="font-semibold">Guest:</span> {{ $reservation->guest_name }}</p>
            </div>

            <div class="mt-6 space-y-3">
                <a href="{{ $trackingUrl }}" class="inline-flex rounded-lg bg-[#00491E] px-5 py-3 font-semibold text-white">Track reservation</a>
                @if($accountReservationUrl)
                    <a href="{{ $accountReservationUrl }}" class="inline-flex rounded-lg border border-gray-300 px-5 py-3 font-semibold text-gray-700">Return to reservation</a>
                @else
                    <a href="{{ route('guest.home', [], false) }}" class="inline-flex rounded-lg border border-gray-300 px-5 py-3 font-semibold text-gray-700">Return to homepage</a>
                @endif
            </div>
        </div>
    </section>
@endsection
