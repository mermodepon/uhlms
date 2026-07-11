@extends('layouts.guest')

@section('title', 'Reservation '.$reservation->reference_number)
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
        <div class="rounded-xl bg-gradient-to-r from-[#00491E] to-[#02681E] p-6 text-white">
            <p class="text-sm text-green-100">Reservation</p>
            <h1 class="text-3xl font-bold">{{ $reservation->reference_number }}</h1>
            <p class="mt-2 text-green-100">{{ $reservation->check_in_date->format('F j, Y') }} to {{ $reservation->check_out_date->format('F j, Y') }}</p>
        </div>

        @include('guest.partials.flash-messages', [
            'wrap' => false,
            'containerClass' => 'space-y-3',
        ])

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xl font-bold text-[#00491E]">Status</h2>
                @include('guest.partials.reservation-status-badge', ['status' => $reservation->status])
                @if($reservation->status === 'pending')
                    <p class="mt-4 text-sm text-gray-600">Your request is under review. Estimated processing time is 1-2 business days.</p>
                @endif
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xl font-bold text-[#00491E]">Room Request</h2>
                <p class="text-gray-800">{{ $reservation->requested_room_summary }}</p>
                <p class="mt-2 text-sm text-gray-600">{{ $reservation->number_of_occupants }} occupant(s)</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-xl font-bold text-[#00491E]">Submitted Guest Details</h2>
            <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div><dt class="font-semibold text-gray-500">Name</dt><dd>{{ $reservation->guest_name }}</dd></div>
                <div><dt class="font-semibold text-gray-500">Email</dt><dd>{{ $reservation->guest_email }}</dd></div>
                <div><dt class="font-semibold text-gray-500">Mobile</dt><dd>{{ $reservation->guest_phone ?: '-' }}</dd></div>
                <div><dt class="font-semibold text-gray-500">Purpose</dt><dd>{{ $reservation->purpose ?: '-' }}</dd></div>
                <div class="md:col-span-2"><dt class="font-semibold text-gray-500">Special Requests</dt><dd>{{ $reservation->special_requests ?: '-' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#00491E]">Stay Feedback</h2>
                    <p class="text-sm text-gray-600">Feedback is reviewed internally by homestay staff.</p>
                </div>
                @if($reservation->canReceiveFeedbackFrom($account))
                    <a href="{{ route('guest.account.feedback.create', $reservation, false) }}" class="inline-flex rounded-lg bg-[#00491E] px-4 py-2 font-semibold text-white">Leave Feedback</a>
                @endif
            </div>

            @if($reservation->feedback)
                @php
                    $feedback = $reservation->feedback;
                    $categoryRatings = [
                        'Cleanliness' => $feedback->cleanliness_rating,
                        'Comfort' => $feedback->comfort_rating,
                        'Staff / Service' => $feedback->service_rating,
                        'Value' => $feedback->value_rating,
                        'Booking Experience' => $feedback->booking_experience_rating,
                    ];
                @endphp
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold">Feedback submitted</p>
                            <div class="mt-1 flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
                                <span class="text-sm font-medium text-green-900">Overall</span>
                                @include('guest.partials.star-rating', ['rating' => $feedback->overall_rating, 'label' => 'Overall rating '.$feedback->overall_rating.' out of 5'])
                            </div>
                        </div>
                        <p class="text-sm text-green-800">{{ optional($feedback->submitted_at)->format('M d, Y g:i A') }}</p>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 rounded-lg bg-white/70 p-4 sm:grid-cols-2">
                        @foreach($categoryRatings as $label => $rating)
                            <div>
                                <p class="text-sm font-semibold text-gray-700">{{ $label }}</p>
                                @if(filled($rating))
                                    @include('guest.partials.star-rating', ['rating' => $rating, 'label' => $label.' rating '.$rating.' out of 5'])
                                @else
                                    <span class="mt-1 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">Not rated</span>
                                @endif
                            </div>
                        @endforeach

                        <div>
                            <p class="text-sm font-semibold text-gray-700">Would stay again</p>
                            <span class="mt-1 inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                @if(is_null($feedback->would_stay_again))
                                    Not answered
                                @else
                                    {{ $feedback->would_stay_again ? 'Yes' : 'No' }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg bg-white/70 p-4">
                        <p class="text-sm font-semibold text-gray-700">Comments</p>
                        <p class="mt-1 text-sm">{{ filled($feedback->comments) ? $feedback->comments : 'No written comments.' }}</p>
                    </div>
                </div>
            @elseif($reservation->status === 'checked_out' && ! $account->hasVerifiedEmail())
                <p class="text-sm text-gray-600">Please verify your email before leaving feedback for this completed stay.</p>
            @elseif($reservation->status !== 'checked_out')
                <p class="text-sm text-gray-600">Feedback becomes available after checkout.</p>
            @else
                <p class="text-sm text-gray-600">You can now leave feedback for this completed stay.</p>
            @endif
        </div>

        <a href="{{ route('guest.account.reservations', [], false) }}" class="inline-flex rounded-lg bg-gray-200 px-5 py-3 font-bold text-gray-800">Back to Reservations</a>
    </section>
@endsection
