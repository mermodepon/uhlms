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

        @if($reference && !$reservation)
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8">
                <p class="font-medium">Reservation not found</p>
                <p class="text-sm mt-1">No reservation matches the reference number "{{ $reference }}". Please check and try again.</p>
            </div>
        @endif

        @if($reservation)
            {{-- Status Timeline --}}
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-[#00491E]">Reservation {{ $reservation->reference_number }}</h2>
                    @php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                            'approved' => 'bg-blue-100 text-blue-800 border-blue-300',
                            'declined' => 'bg-red-100 text-red-800 border-red-300',
                            'cancelled' => 'bg-gray-100 text-gray-800 border-gray-300',
                            'checked_in' => 'bg-green-100 text-green-800 border-green-300',
                            'checked_out' => 'bg-gray-100 text-gray-600 border-gray-300',
                        ];
                        $statusLabels = [
                            'pending' => 'Pending Review',
                            'approved' => 'Approved',
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
                    $steps = ['pending', 'approved', 'checked_in', 'checked_out'];
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
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Name</dt>
                            <dd class="font-medium">{{ $reservation->guest_name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Email</dt>
                            <dd>{{ $reservation->guest_email }}</dd>
                        </div>
                        @if($reservation->guest_phone)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Phone</dt>
                                <dd>{{ $reservation->guest_phone }}</dd>
                            </div>
                        @endif
                        @if($reservation->guest_gender)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Gender</dt>
                                <dd>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $reservation->guest_gender === 'Male' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $reservation->guest_gender === 'Female' ? 'bg-pink-100 text-pink-800' : '' }}
                                        {{ $reservation->guest_gender === 'Other' ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ $reservation->guest_gender }}
                                    </span>
                                </dd>
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

            {{-- Room Assignment --}}
            @if($reservation->roomAssignments->isNotEmpty())
                <div class="bg-white rounded-xl shadow-md p-6 mt-8">
                    <h3 class="font-bold text-[#00491E] mb-4">Assigned Room(s)</h3>
                    <div class="space-y-3">
                        @foreach($reservation->roomAssignments as $assignment)
                            <div class="flex items-center justify-between bg-[#00491E]/5 rounded-lg p-3">
                                <div>
                                    <span class="font-bold text-[#00491E]">Room {{ $assignment->room->room_number }}</span>
                                    <span class="text-gray-500 text-sm ml-2">{{ $assignment->room->roomType->name ?? '' }}</span>
                                </div>
                                <span class="text-sm text-gray-500">Assigned {{ $assignment->assigned_at->format('M d, Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Stay Logs --}}
            @if($reservation->stayLogs->isNotEmpty())
                <div class="bg-white rounded-xl shadow-md p-6 mt-8">
                    <h3 class="font-bold text-[#00491E] mb-4">Stay Log</h3>
                    <div class="space-y-3">
                        @foreach($reservation->stayLogs as $log)
                            <div class="border rounded-lg p-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="font-medium">Room {{ $log->room->room_number }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 mt-2 text-gray-600">
                                    <div>
                                        <span class="text-gray-400">Checked In:</span>
                                        {{ $log->checked_in_at ? $log->checked_in_at->format('M d, Y g:i A') : '—' }}
                                    </div>
                                    <div>
                                        <span class="text-gray-400">Checked Out:</span>
                                        {{ $log->checked_out_at ? $log->checked_out_at->format('M d, Y g:i A') : 'Still checked in' }}
                                    </div>
                                </div>
                                @if($log->remarks)
                                    <p class="text-gray-500 mt-2 text-xs">{{ $log->remarks }}</p>
                                @endif
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
