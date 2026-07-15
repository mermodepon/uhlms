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

        @include('guest.partials.reservation-progress', ['reservation' => $reservation])

        <div class="grid grid-cols-1 gap-4">
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

        @php
            $onlinePayments = $reservation->payments
                ->where('gateway', 'paymongo')
                ->sortByDesc(fn ($payment) => $payment->received_at ?? $payment->created_at);
            $paymentSummary = $reservation->guestPaymentSummary();
        @endphp
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-xl font-bold text-[#00491E]">Online Payments</h2>
            <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div><dt class="font-semibold text-gray-500">Payment status</dt><dd>{{ $paymentSummary['status_label'] }}</dd></div>
                <div><dt class="font-semibold text-gray-500">{{ $paymentSummary['balance_label'] }}</dt><dd>PHP {{ number_format($paymentSummary['remaining'], 2) }}</dd></div>
            </dl>

            @if(! $paymentSummary['is_finalized'] && $paymentSummary['paid'] > 0)
                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <p><span class="font-semibold">Estimated total:</span> PHP {{ number_format($paymentSummary['total'], 2) }}</p>
                    <p><span class="font-semibold">Deposit paid:</span> PHP {{ number_format($paymentSummary['paid'], 2) }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ $paymentSummary['note'] }}</p>
                </div>
            @endif

            @if($canInitiateDepositPayment)
                <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-5 text-center text-blue-950">
                    <h3 class="font-bold">Complete your deposit payment</h3>
                    <p class="mt-1 text-sm">Your room hold is active. Use secure online payment to confirm your reservation.</p>
                    <a href="{{ route('guest.account.reservations.deposit-payment', $reservation, false) }}" class="mt-4 inline-flex rounded-lg bg-[#00491E] px-4 py-2 font-semibold text-white">Pay deposit</a>
                </div>
            @endif

            @if($pendingCheckInBalancePayment)
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-5 text-center text-amber-950">
                    <h3 class="font-bold">Pay remaining balance</h3>
                    <p class="mt-1 text-sm">PHP {{ number_format((float) $pendingCheckInBalancePayment->amount, 2) }} is ready for secure online payment.</p>
                    <img src="{{ route('guest.account.reservations.check-in-payment.qr', $reservation, false) }}" alt="QR code for the remaining balance payment" class="mx-auto mt-4 h-56 w-56 rounded-lg border bg-white p-2">
                    <a href="{{ route('guest.account.reservations.check-in-payment.checkout', $reservation, false) }}" class="mt-4 inline-flex rounded-lg bg-[#00491E] px-4 py-2 font-semibold text-white">Pay remaining balance</a>
                    <p class="mt-3 text-xs">This option disappears automatically once payment is confirmed or cancelled. Refresh this page after payment.</p>
                </div>
            @endif

            @forelse($onlinePayments as $payment)
                <div class="mt-4 rounded-lg border border-gray-200 p-4 text-sm">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="font-semibold text-gray-900">{{ data_get($payment->meta, 'payment_type') === 'checkin_balance' ? 'Check-in balance' : ($payment->is_deposit ? 'Deposit' : 'Online payment') }}</span>
                        <span class="font-semibold {{ $payment->gateway_status === 'paid' ? 'text-green-700' : 'text-amber-700' }}">{{ ucfirst($payment->gateway_status ?: $payment->status) }}</span>
                    </div>
                    <p class="mt-1">PHP {{ number_format((float) $payment->amount, 2) }} via {{ $payment->payment_mode ?: 'PayMongo Online' }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ ($payment->received_at ?? $payment->created_at)?->format('M d, Y g:i A') }}</p>
                </div>
            @empty
                <p class="mt-4 text-sm text-gray-600">No online payments have been recorded for this reservation.</p>
            @endforelse
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
