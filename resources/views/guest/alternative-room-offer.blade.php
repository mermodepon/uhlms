@extends('layouts.guest')

@section('title', 'Room Alternative Offer')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-[#00491E]">Room Alternative Offer</h1>
            @include('guest.partials.flash-messages', ['wrap' => false, 'containerClass' => 'mt-6 space-y-3'])
            @if($offer->status !== \App\Models\ReservationAlternativeOffer::STATUS_PENDING)
                <p class="mt-4 text-gray-700">This offer is {{ $offer->status }} and can no longer be changed.</p>
            @else
                <p class="mt-3 text-gray-600">Your requested room type is unavailable. Please review this held alternative before it expires.</p>
                <dl class="mt-6 space-y-3 rounded-xl bg-gray-50 p-5 text-sm">
                    <div class="flex justify-between gap-4"><dt>Alternative room type</dt><dd class="font-semibold">{{ $offer->offeredRoomType->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Stay</dt><dd>{{ $offer->reservation->check_in_date->format('M d, Y') }} - {{ $offer->reservation->check_out_date->format('M d, Y') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Quoted total</dt><dd class="font-semibold">PHP {{ number_format($offer->quoted_total, 2) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Original estimate</dt><dd>PHP {{ number_format($offer->original_total, 2) }}</dd></div>
                </dl>
                @if($offer->message)<p class="mt-5 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">{{ $offer->message }}</p>@endif
                <p class="mt-5 text-sm text-gray-500">This hold expires {{ $offer->expires_at->format('M d, Y g:i A') }}.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <form method="POST" action="{{ $acceptUrl }}">
                        @csrf
                        <button class="rounded-lg bg-[#00491E] px-5 py-3 font-bold text-white">Accept Alternative</button>
                    </form>
                    <form method="POST" action="{{ $declineUrl }}">
                        @csrf
                        <button class="rounded-lg border border-gray-300 px-5 py-3 font-semibold text-gray-700">Decline</button>
                    </form>
                </div>
            @endif
        </div>
    </section>
@endsection
