@extends('layouts.guest')

@section('title', 'Track Reservation')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Track Your Reservation</h1>
            <p class="text-gray-200">Enter your reservation reference number to check the current status.</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {{-- Search Form --}}
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form action="{{ route('guest.track') }}" method="GET" class="flex gap-4">
                <input type="text" name="reference" value="{{ $reference }}"
                       placeholder="Enter reference number (e.g., 2026-0001)"
                       class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                <button type="submit" class="bg-[#00491E] text-white px-6 py-2 rounded-lg hover:bg-[#02681E] transition font-medium">
                    Track
                </button>
            </form>
        </div>

        @if($reference && !$reservation && $expired)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-6 py-4 rounded-xl mb-8">
                <p class="font-medium">Tracking period has ended</p>
                <p class="text-sm mt-1">The tracking record for reservation <strong>{{ $reference }}</strong> is no longer available. Tracking expires 14&nbsp;days after a cancellation or decline, and 30&nbsp;days after check-out.</p>
            </div>
        @elseif($reference && !$reservation)
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8">
                <p class="font-medium">Reservation not found</p>
                <p class="text-sm mt-1">No reservation matches the reference number "{{ $reference }}". Please check and try again.</p>
            </div>
        @endif

        @if($reservation)
            @php
                // --- Privacy masking ---

                // Name: show first name, mask remaining parts (first letter + ***)
                $maskName = function($name) {
                    $parts = explode(' ', trim($name));
                    if (count($parts) <= 1) return $parts[0];
                    $first = array_shift($parts);
                    $masked = array_map(
                        fn($p) => strlen($p) > 0 ? mb_substr($p, 0, 1) . str_repeat('*', min(max(mb_strlen($p) - 1, 2), 4)) : $p,
                        $parts
                    );
                    return $first . ' ' . implode(' ', $masked);
                };

                $maskedName = $maskName($reservation->guest_name);

                // Email: first char of local part + *** @ domain
                $emailParts = explode('@', $reservation->guest_email, 2);
                $maskedEmail = mb_substr($emailParts[0], 0, 1)
                    . str_repeat('*', min(max(mb_strlen($emailParts[0]) - 1, 3), 5))
                    . '@' . ($emailParts[1] ?? '');

                // Phone: mask all digits except the last 4
                $maskedPhone = null;
                if ($reservation->guest_phone) {
                    $digitCount = preg_match_all('/\d/', $reservation->guest_phone);
                    $digitIndex = 0;
                    $maskedPhone = preg_replace_callback('/\d/', function($m) use (&$digitIndex, $digitCount) {
                        $digitIndex++;
                        return ($digitIndex <= $digitCount - 4) ? '*' : $m[0];
                    }, $reservation->guest_phone);
                }

                // Room number: show first char, mask the rest
                $maskRoom = fn($num) => mb_substr($num, 0, 1) . str_repeat('*', min(max(mb_strlen($num) - 1, 2), 3));

                // Assignment name masking
                $maskAssignmentName = fn($a) => $maskName(
                    trim($a->guest_first_name . ' ' . $a->guest_last_name) ?: $reservation->guest_name
                );
            @endphp

            {{-- Status Timeline --}}
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-[#00491E]">Reservation {{ $reservation->reference_number }}</h2>
                    @php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                            'approved' => 'bg-blue-100 text-blue-800 border-blue-300',
                            'pending_payment' => 'bg-amber-100 text-amber-800 border-amber-300',
                            'declined' => 'bg-red-100 text-red-800 border-red-300',
                            'cancelled' => 'bg-gray-100 text-gray-800 border-gray-300',
                            'checked_in' => 'bg-green-100 text-green-800 border-green-300',
                            'checked_out' => 'bg-gray-100 text-gray-600 border-gray-300',
                        ];
                        $statusLabels = [
                            'pending' => 'Pending Review',
                            'approved' => 'Approved',
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
                    $steps = ['pending', 'approved', 'pending_payment', 'checked_in', 'checked_out'];
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

                @if(in_array($reservation->status, ['declined', 'cancelled']) && $reservation->admin_notes)
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <p class="font-medium text-red-800">Staff Notes:</p>
                        <p class="text-red-700 text-sm mt-1">{{ $reservation->admin_notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Reservation Details --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold text-[#00491E] mb-4">Guest Information</h3>
                    <p class="text-xs text-gray-400 mb-3">Some details are partially masked for privacy.</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Name</dt>
                            <dd class="font-medium">{{ $maskedName }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Email</dt>
                            <dd>{{ $maskedEmail }}</dd>
                        </div>
                        @if($reservation->guest_phone)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Phone</dt>
                                <dd>{{ $maskedPhone }}</dd>
                            </div>
                        @endif
                        @if($reservation->guest_gender)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Gender</dt>
                                <dd class="font-medium">{{ $reservation->guest_gender }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold text-[#00491E] mb-4">Stay Details</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Room Type</dt>
                            <dd class="font-medium">{{ $reservation->preferredRoomType->name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Check-in</dt>
                            <dd>{{ $reservation->check_in_date->format('M d, Y') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Check-out</dt>
                            <dd>{{ $reservation->check_out_date->format('M d, Y') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Duration</dt>
                            <dd>{{ $reservation->nights }} {{ Str::plural('night', $reservation->nights) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Occupants</dt>
                            <dd>{{ $reservation->number_of_occupants }}</dd>
                        </div>
                        @if($reservation->purpose)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Purpose</dt>
                                <dd>{{ ucfirst($reservation->purpose) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Room Assignment - Improved Table View --}}
            @if($reservation->roomAssignments->isNotEmpty())
                <div class="bg-white rounded-xl shadow-md p-6 mt-8">
                    <h3 class="font-bold text-[#00491E] mb-4">Guest Room Assignments</h3>
                    
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
            @php
                $remarksGrouped = $reservation->roomAssignments
                    ->where('remarks')
                    ->groupBy('room_id')
                    ->map(fn($group) => [
                        'room' => $group->first()->room,
                        'remarks' => $group->first()->remarks,
                        'guests' => $group->map(fn($a) => $maskName(trim($a->guest_first_name . ' ' . $a->guest_last_name)))->filter()->all()
                    ]);
            @endphp
            @if($remarksGrouped->isNotEmpty())
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

            <div class="text-center mt-8">
                <p class="text-gray-500 text-sm">Submitted on {{ $reservation->created_at->format('F d, Y \\a\\t g:i A') }}</p>
            </div>
        @endif
    </section>
@endsection
