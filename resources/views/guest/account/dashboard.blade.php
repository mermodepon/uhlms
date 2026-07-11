@extends('layouts.guest')

@section('title', 'Guest Dashboard')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Welcome, {{ $account->first_name ?: $account->name }}</h1>
            <p class="text-gray-200">Manage your profile and reservation history.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
        @include('guest.partials.flash-messages', [
            'wrap' => false,
            'containerClass' => 'space-y-3',
        ])

        @unless($account->hasVerifiedEmail())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                <form method="POST" action="{{ route('guest.account.verification.send', [], false) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    @csrf
                    <span>Please verify your email to claim matching past reservations.</span>
                    <button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Resend Verification</button>
                </form>
            </div>
        @endunless

        @if($claimable->isNotEmpty())
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-blue-900">
                <form method="POST" action="{{ route('guest.account.reservations.claim', [], false) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    @csrf
                    <span>{{ $claimable->count() }} previous reservation(s) match your verified email.</span>
                    <button class="rounded-lg bg-[#00491E] px-4 py-2 font-semibold text-white">Link to My Account</button>
                </form>
            </div>
        @endif

        @php
            $statCards = [
                'upcoming' => ['label' => 'Upcoming', 'accent' => '#919F02'],
                'pending' => ['label' => 'Pending', 'accent' => '#fbbf24'],
                'awaiting_alternative_confirmation' => ['label' => 'Alternative Offer Pending', 'accent' => '#f59e0b'],
                'active' => ['label' => 'Active', 'accent' => '#10B981'],
                'completed' => ['label' => 'Completed', 'accent' => '#94a3b8'],
            ];
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($statCards as $key => $card)
                <div class="rounded-xl border border-gray-200 border-t-4 bg-white p-5 shadow-sm" style="border-top-color: {{ $card['accent'] }};">
                    <div class="text-sm font-medium text-gray-500">{{ $card['label'] }}</div>
                    <div class="mt-2 text-3xl font-bold" style="color: {{ $card['accent'] }};">{{ $stats[$key] }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <h2 class="text-xl font-bold text-[#00491E]">Recent Reservations</h2>
                    <a href="{{ route('guest.account.reservations', [], false) }}" class="text-sm font-semibold text-[#00491E] hover:underline">View all</a>
                </div>
                <div class="space-y-3">
                    @forelse($reservations->take(5) as $reservation)
                        <a href="{{ route('guest.account.reservations.show', $reservation, false) }}" class="block rounded-lg border border-gray-200 p-4 hover:border-[#00491E]">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="font-bold text-gray-900">{{ $reservation->reference_number }}</div>
                                    <div class="text-sm text-gray-600">{{ $reservation->check_in_date->format('M d, Y') }} to {{ $reservation->check_out_date->format('M d, Y') }}</div>
                                </div>
                                <div class="flex flex-col gap-2 sm:items-end">
                                    @include('guest.partials.reservation-status-badge', ['status' => $reservation->status])
                                    @if($reservation->canReceiveFeedbackFrom($account))
                                        <span class="text-xs font-semibold text-[#00491E]">Feedback available</span>
                                    @elseif($reservation->feedback)
                                        <span class="text-xs font-semibold text-green-700">Feedback submitted</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <p class="text-gray-600">No linked reservations yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-[#00491E] mb-4">Profile</h2>
                <div class="space-y-2 text-sm text-gray-700">
                    <p><strong>Email:</strong> {{ $account->email }}</p>
                    <p><strong>Mobile:</strong> {{ $account->phone ?: '-' }}</p>
                    <p><strong>Status:</strong> {{ $account->hasVerifiedEmail() ? 'Verified' : 'Unverified' }}</p>
                </div>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('guest.account.profile', [], false) }}" class="inline-flex justify-center rounded-lg bg-[#00491E] px-4 py-2 font-semibold text-white">Edit Profile</a>
                    <a href="{{ route('guest.account.support.index', [], false) }}" class="inline-flex justify-center rounded-lg border border-[#00491E] px-4 py-2 font-semibold text-[#00491E] hover:bg-[#00491E]/5">Support</a>
                </div>
            </div>
        </div>
    </section>
@endsection
