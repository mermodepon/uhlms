@extends('layouts.guest')

@section('title', 'My Reservations')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[#00491E]">My Reservations</h1>
                <p class="text-gray-600">View your linked reservation requests and stays.</p>
            </div>
            <a href="{{ route('guest.reserve', [], false) }}" class="rounded-lg bg-[#FFC600] px-5 py-3 font-bold text-[#00491E]">New Reservation</a>
        </div>

        @include('guest.partials.flash-messages', ['wrap' => false, 'containerClass' => 'mb-6 space-y-3'])

        <div class="space-y-4">
            @forelse($reservations as $reservation)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-bold text-gray-900">{{ $reservation->reference_number }}</div>
                            <div class="text-sm text-gray-600">{{ $reservation->preferredRoomType?->name ?? 'Room request' }} &bull; {{ $reservation->requested_room_summary }}</div>
                            <div class="text-sm text-gray-600">{{ $reservation->check_in_date->format('M d, Y') }} to {{ $reservation->check_out_date->format('M d, Y') }}</div>
                        </div>
                        <div class="flex flex-col gap-2 sm:items-end">
                            @include('guest.partials.reservation-status-badge', ['status' => $reservation->status])
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('guest.account.reservations.show', $reservation, false) }}" class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-200">View Details</a>
                                @if($reservation->canReceiveFeedbackFrom($account))
                                    <a href="{{ route('guest.account.feedback.create', $reservation, false) }}" class="rounded-lg bg-[#00491E] px-3 py-2 text-sm font-semibold text-white hover:bg-[#02681E]">Leave Feedback</a>
                                @elseif($reservation->feedback)
                                    <span class="rounded-lg bg-green-50 px-3 py-2 text-sm font-semibold text-green-800">Feedback submitted</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
                    <p class="text-gray-600">No linked reservations yet.</p>
                    <a href="{{ route('guest.reserve', [], false) }}" class="mt-4 inline-flex rounded-lg bg-[#00491E] px-5 py-3 font-bold text-white">Request a Stay</a>
                </div>
            @endforelse
        </div>
    </section>
@endsection
