@extends('layouts.guest')

@section('title', 'Track Reservation')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Track Your Reservation</h1>
            <p class="text-gray-200">Use your reservation reference number and guest email address, or open the secure tracking link sent to your email.</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {{-- Search Form --}}
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form action="{{ route('guest.track', [], false) }}" method="GET" class="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-4">
                <input type="text" name="reference" value="{{ $reference }}"
                       placeholder="Reference number (e.g., 2026-0001)"
                       class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                <input type="email" name="guest_email" value="{{ $guestEmail ?? '' }}"
                       placeholder="Guest email used on the reservation"
                       class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                <button type="submit" class="bg-[#00491E] text-white px-6 py-2 rounded-lg hover:bg-[#02681E] transition font-medium">
                    Track
                </button>
            </form>
            @if ($errors->any())
                <p class="text-sm text-red-600 mt-3">Please enter both your reservation reference number and guest email address.</p>
            @endif
        </div>

        @if($reference && !$reservation && $expired)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-6 py-4 rounded-xl mb-8">
                <p class="font-medium">Tracking period has ended</p>
                <p class="text-sm mt-1">The tracking record for reservation <strong>{{ $reference }}</strong> is no longer available. Tracking expires 14&nbsp;days after a cancellation or decline, and 30&nbsp;days after check-out.</p>
            </div>
        @elseif($reference && !$reservation)
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8">
                <p class="font-medium">Reservation not found</p>
                <p class="text-sm mt-1">No reservation matches reference <strong>{{ $reference }}</strong> with the guest email you entered. Please check both and try again.</p>
            </div>
        @endif

        @if($reservation)
            @php
                // Room number: show first char, mask the rest
                $maskRoom = fn($num) => mb_substr($num, 0, 1) . str_repeat('*', min(max(mb_strlen($num) - 1, 2), 3));

                // Assignment name masking
                $maskAssignmentName = function ($a) use ($reservation) {
                    $name = trim($a->guest_first_name . ' ' . $a->guest_last_name) ?: $reservation->guest_name;
                    $parts = explode(' ', trim($name));
                    if (count($parts) <= 1) return $parts[0];
                    $first = array_shift($parts);
                    $masked = array_map(
                        fn($p) => strlen($p) > 0 ? mb_substr($p, 0, 1) . str_repeat('*', min(max(mb_strlen($p) - 1, 2), 4)) : $p,
                        $parts
                    );
                    return $first . ' ' . implode(' ', $masked);
                };

                $statusGuidance = [
                    'pending' => 'Your request is waiting for staff review. Please watch your email for approval or follow-up instructions.',
                    'approved' => 'Your reservation has been approved. Watch your email for payment instructions or additional confirmation details.',
                    'confirmed' => 'Your reservation is confirmed. Please keep monitoring your email for any payment reminders or arrival instructions.',
                    'pending_payment' => 'Your reservation is waiting for payment. Complete the payment step shown below to avoid delays.',
                    'declined' => 'This reservation request was declined. Please contact the homestay staff if you need clarification or would like to submit a new request.',
                    'cancelled' => 'This reservation has been cancelled. Contact staff if you believe this was made in error.',
                    'checked_in' => 'You are currently checked in. If you need help during your stay, please contact the homestay staff.',
                    'checked_out' => 'This reservation has been completed. Thank you for staying with us.',
                ];

                $summaryFields = [
                    'room_type' => $reservation->preferredRoomType?->name,
                    'check_in' => $reservation->check_in_date->format('M d, Y'),
                    'check_out' => $reservation->check_out_date->format('M d, Y'),
                ];

                $showAssignments = $reservation->roomAssignments->isNotEmpty()
                    && in_array($reservation->status, ['checked_in', 'checked_out'], true);

                $remarksGrouped = $reservation->roomAssignments
                    ->where('remarks')
                    ->groupBy('room_id')
                    ->map(fn($group) => [
                        'room' => $group->first()->room,
                        'remarks' => $group->first()->remarks,
                        'guests' => $group->map(fn($a) => $maskAssignmentName($a))->filter()->all()
                    ]);

                $showAssignmentNotes = $showAssignments && $remarksGrouped->isNotEmpty();
                $showSubmittedAt = in_array($reservation->status, ['pending', 'approved', 'confirmed', 'pending_payment'], true);
                $guidanceTone = in_array($reservation->status, ['declined', 'cancelled'], true)
                    ? 'bg-red-50 border-red-200 text-red-800'
                    : 'bg-blue-50 border-blue-200 text-blue-800';
            @endphp

            {{-- Status Timeline --}}
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-[#00491E]">Reservation {{ $reservation->reference_number }}</h2>
                    @php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                            'approved' => 'bg-blue-100 text-blue-800 border-blue-300',
                            'confirmed' => 'bg-green-100 text-green-800 border-green-300',
                            'pending_payment' => 'bg-amber-100 text-amber-800 border-amber-300',
                            'declined' => 'bg-red-100 text-red-800 border-red-300',
                            'cancelled' => 'bg-gray-100 text-gray-800 border-gray-300',
                            'checked_in' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                            'checked_out' => 'bg-gray-100 text-gray-600 border-gray-300',
                        ];
                        $statusLabels = [
                            'pending' => 'Pending Review',
                            'approved' => 'Approved',
                            'confirmed' => 'Confirmed',
                            'pending_payment' => 'Pending Payment',
                            'declined' => 'Declined',
                            'cancelled' => 'Cancelled',
                            'checked_in' => 'Checked In',
                            'checked_out' => 'Checked out',
                        ];
                    @endphp
                    <span class="px-4 py-1 rounded-full border font-semibold text-sm {{ $statusColors[$reservation->status] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ $statusLabels[$reservation->status] ?? ucfirst(str_replace('_', ' ', $reservation->status)) }}
                    </span>
                </div>

                {{-- Progress Bar --}}
                @php
                    $steps = ['pending', 'approved', 'confirmed', 'pending_payment', 'checked_in', 'checked_out'];
                    $currentIndex = array_search($reservation->status, $steps);
                    if ($reservation->status === 'declined' || $reservation->status === 'cancelled') {
                        $currentIndex = -1;
                    }
                @endphp
                @if(!in_array($reservation->status, ['declined', 'cancelled']))
                    <div class="flex items-center justify-between mb-8">
                        @foreach($steps as $i => $step)
                            <div class="flex-1 {{ $i < count($steps) - 1 ? 'relative' : '' }}">
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                                        {{ $i <= $currentIndex ? 'bg-[#00491E] text-white' : 'bg-gray-200 text-gray-500' }}">
                                        @if($i < $currentIndex)
                                            ✓
                                        @else
                                            {{ $i + 1 }}
                                        @endif
                                    </div>
                                    <span class="text-xs mt-1 {{ $i <= $currentIndex ? 'text-[#00491E] font-medium' : 'text-gray-400' }}">
                                        {{ $statusLabels[$step] ?? ucfirst(str_replace('_', ' ', $step)) }}
                                    </span>
                                </div>
                                @if($i < count($steps) - 1)
                                    <div class="absolute top-4 left-1/2 w-full h-0.5 {{ $i < $currentIndex ? 'bg-[#00491E]' : 'bg-gray-200' }}"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>

            {{-- Reservation Summary --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold text-[#00491E] mb-4">What Happens Next</h3>
                    <div class="rounded-lg border p-4 text-sm {{ $guidanceTone }}">
                        {{ $statusGuidance[$reservation->status] ?? 'Please monitor this page and your email for updates to your reservation.' }}
                    </div>
                    <p class="text-xs text-gray-500 mt-3">For privacy, this page now focuses on reservation status and essential next steps only.</p>
                </div>

                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold text-[#00491E] mb-4">Reservation Summary</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Room Type</dt>
                            <dd class="font-medium">{{ $summaryFields['room_type'] ?? 'To be assigned' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Check-in</dt>
                            <dd>{{ $summaryFields['check_in'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Check-out</dt>
                            <dd>{{ $summaryFields['check_out'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Reference</dt>
                            <dd class="font-medium">{{ $reservation->reference_number }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Online Payment Section with QR Code --}}
            @php
                $onlinePaymentsEnabled = \App\Models\Setting::isOnlinePaymentsEnabled();
                $hasValidPaymentLink = $reservation->payment_link_token && $reservation->isPaymentLinkValid();
                $canPay = in_array($reservation->status, ['pending', 'approved', 'confirmed']);
                
                // Check if a payment has been made (deposit or full)
                $gatewayPayment = $reservation->payments
                    ->where('gateway', 'paymongo')
                    ->where('gateway_status', 'paid')
                    ->first()
                    ?? $reservation->payments
                        ->where('gateway', 'paymongo')
                        ->first();
                
                $depositPaid = $gatewayPayment && $gatewayPayment->gateway_status === 'paid';
                $paymentPending = $gatewayPayment && $gatewayPayment->gateway_status === 'pending';
                $paymentFailed = $gatewayPayment && $gatewayPayment->gateway_status === 'failed';
                
                // Show deposit status section whenever a deposit EXISTS (regardless of toggle)
                $showDepositStatus = $depositPaid || $paymentPending || $paymentFailed;
                // Show payment link/QR only when toggle is ON and link is valid
                $showPaymentLink = $onlinePaymentsEnabled && $hasValidPaymentLink && $canPay && !$depositPaid && !$paymentPending;
            @endphp

            @if($showDepositStatus || $showPaymentLink)
                <div class="bg-white rounded-xl shadow-md p-6 mt-8">
                    @if($depositPaid)
                        {{-- Payment Successful --}}
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-green-700 mb-2">
                                @if($gatewayPayment->is_deposit)
                                    Deposit Payment Received
                                @else
                                    ✓ Full Payment Received
                                @endif
                            </h3>
                            <p class="text-gray-600 text-sm mb-4">
                                @if($gatewayPayment->is_deposit)
                                    Your online deposit payment has been successfully processed.
                                @else
                                    Your online full payment has been successfully processed. No additional payment needed at check-in.
                                @endif
                            </p>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 inline-block">
                                <p class="text-sm text-green-800">
                                    <strong>Amount Paid:</strong> ₱{{ number_format($gatewayPayment->amount, 2) }}<br>
                                    <strong>Payment Type:</strong> {{ $gatewayPayment->is_deposit ? 'Deposit' : 'Full Payment' }}<br>
                                    <strong>Payment Method:</strong> {{ ucfirst($gatewayPayment->payment_mode) }}<br>
                                    <strong>Transaction ID:</strong> {{ $gatewayPayment->gateway_payment_id }}
                                </p>
                            </div>
                            @php
                                $estimatedTotal = $reservation->calculateDepositAmount() > 0
                                    ? round($gatewayPayment->amount / (($reservation->deposit_percentage ?? \App\Models\Setting::getDefaultDepositPercentage()) / 100), 2)
                                    : 0;
                                $estimatedRemaining = max(0, $estimatedTotal - $gatewayPayment->amount);
                            @endphp
                            @if($gatewayPayment->is_deposit && $estimatedRemaining > 0)
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 inline-block mt-4">
                                    <p class="text-sm text-gray-700">
                                        <strong>Estimated Total:</strong> ₱{{ number_format($estimatedTotal, 2) }}<br>
                                        <strong>Deposit Paid:</strong> -₱{{ number_format($gatewayPayment->amount, 2) }}<br>
                                        <strong>Estimated Remaining Balance:</strong> ₱{{ number_format($estimatedRemaining, 2) }}
                                    </p>
                                </div>
                            @endif
                            <p class="text-gray-500 text-xs mt-4">Please complete the remaining balance upon check-in at our facility.</p>
                        </div>
                    @elseif($paymentPending)
                        {{-- Payment Pending --}}
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 mb-4">
                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-yellow-700 mb-2">⏳ Payment Processing</h3>
                            <p class="text-gray-600 text-sm">Your payment is being processed. Please wait a few moments and refresh this page.</p>
                        </div>
                    @elseif($paymentFailed)
                        {{-- Payment Failed --}}
                        <div class="text-center mb-6">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-4">
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-red-700 mb-2">✗ Payment Failed</h3>
                            <p class="text-gray-600 text-sm mb-4">Your previous payment attempt was unsuccessful. Please try again using the QR code or link below.</p>
                        </div>
                    @endif

                    @if($showPaymentLink)
                        {{-- Show Payment QR Code and Link --}}
                        <div class="text-center">
                            @if(!$paymentFailed)
                                <h3 class="text-xl font-bold text-[#00491E] mb-2">💳 Complete Your Deposit Payment</h3>
                                <p class="text-gray-600 text-sm mb-6">Scan the QR code below or click the payment link to pay your deposit online.</p>
                            @endif

                            {{-- QR Code --}}
                            <div class="flex justify-center mb-6">
                                <div class="inline-block">
                                    <div class="bg-gradient-to-br from-[#00491E] to-[#02681E] p-6 rounded-2xl shadow-lg">
                                        <div class="bg-white p-4 rounded-xl">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($reservation->generatePaymentLink(false)) }}"
                                                 alt="Payment QR Code"
                                                 class="w-64 h-64 mx-auto">
                                        </div>
                                        <p class="text-white text-sm mt-4 font-medium">Scan with your phone camera</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Payment Link Button --}}
                            <div class="space-y-4">
                                <a href="{{ $reservation->generatePaymentLink(false) }}"
                                   class="inline-block bg-gradient-to-r from-[#00491E] to-[#02681E] text-white px-8 py-4 rounded-xl font-bold text-lg hover:shadow-lg transition-all transform hover:scale-105">
                                    🔒 Pay Deposit Now
                                </a>

                                {{-- Payment Methods --}}
                                <div class="flex flex-wrap justify-center gap-3 items-center">
                                    <span class="text-sm text-gray-500">Pay with:</span>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">GCash</span>
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">Maya</span>
                                    <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">GrabPay</span>
                                    <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">Credit/Debit Card</span>
                                </div>

                                {{-- Expiry Warning --}}
                                @if($reservation->payment_link_expires_at)
                                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 inline-block">
                                        <p class="text-xs text-amber-800">
                                            <strong>⏰ Payment link expires:</strong> {{ $reservation->payment_link_expires_at->format('M d, Y \a\t g:i A') }}
                                        </p>
                                    </div>
                                @endif

                                {{-- Info Box --}}
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 max-w-md mx-auto text-left">
                                    <p class="text-sm text-blue-800">
                                        <strong>ℹ️ What happens next:</strong>
                                    </p>
                                    <ol class="text-xs text-blue-700 mt-2 space-y-1 ml-4 list-decimal">
                                        <li>Scan the QR code or click "Pay Deposit Now"</li>
                                        <li>Choose your preferred payment method</li>
                                        <li>Complete the secure payment</li>
                                        <li>Your reservation will be confirmed</li>
                                        <li>Pay the remaining balance when you check in</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Room Assignment - Improved Table View --}}
            @if($showAssignments)
                <div class="bg-white rounded-xl shadow-md p-6 mt-8">
                    <h3 class="font-bold text-[#00491E] mb-4">Room Assignments</h3>
                    
                    {{-- Summary Card --}}
                    <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-sm text-blue-800">
                            <span class="font-semibold">{{ $reservation->roomAssignments->count() }}</span> room assignment(s) 
                            for <span class="font-semibold">{{ $reservation->number_of_occupants }}</span> guest(s)
                            @if($reservation->roomAssignments->count() > $reservation->number_of_occupants)
                                <br><span class="text-orange-600">⚠️ Note: More room assignments than expected. Please contact staff if this is incorrect.</span>
                            @endif
                        </p>
                    </div>

                    {{-- Table View --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Guest Name</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">{{ $reservation->preferredRoomType?->isPrivate() ? 'Room' : 'Room & Bed' }}</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Check-in Status</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Assigned Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reservation->roomAssignments as $assignment)
                                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                        <td class="py-3 px-4">
                                            <span class="font-medium text-[#00491E]">
                                                {{ $maskAssignmentName($assignment) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div>
                                                <span class="font-semibold">Room {{ $maskRoom($assignment->room->room_number) }}</span>
                                                <span class="text-gray-500 text-xs ml-1">({{ $assignment->room->roomType->name ?? 'Unknown' }})</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($assignment->checked_out_at || $assignment->status === 'checked_out')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                    ✓ Checked out
                                                </span>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    {{ optional($assignment->checked_out_at)->format('M d, g:i A') ?? 'Completed' }}
                                                </div>
                                            @elseif($assignment->checked_in_at)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    ✓ Checked in
                                                </span>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    {{ $assignment->checked_in_at->format('M d, g:i A') }}
                                                </div>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    ⏱ Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-gray-600">
                                            {{ $assignment->assigned_at->format('M d, Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Additional Notes --}}
            @if($showAssignmentNotes)
                <div class="bg-white rounded-xl shadow-md p-6 mt-8">
                    <h3 class="font-bold text-[#00491E] mb-4">Assignment Notes</h3>
                    <div class="space-y-3">
                        @foreach($remarksGrouped as $note)
                            <div class="border-l-4 border-[#00491E] bg-blue-50 p-4 rounded">
                                <p class="text-sm font-medium text-[#00491E]">
                                    Room {{ $maskRoom($note['room']->room_number) }}
                                    @if(!empty($note['guests']))
                                        <span class="text-gray-600 font-normal text-xs ml-2">({{ implode(', ', $note['guests']) }})</span>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-700 mt-1">{{ $note['remarks'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($showSubmittedAt)
                <div class="text-center mt-8">
                <p class="text-gray-500 text-sm">Submitted on {{ $reservation->created_at->format('F d, Y \\a\\t g:i A') }}</p>
                </div>
            @endif
        @endif
    </section>
@endsection
