@extends('layouts.guest')

@section('title', 'Leave Feedback')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
        <div class="rounded-xl bg-gradient-to-r from-[#00491E] to-[#02681E] p-6 text-white">
            <p class="text-sm text-green-100">Internal stay feedback</p>
            <h1 class="text-3xl font-bold">How was your stay?</h1>
            <p class="mt-2 text-green-100">{{ $reservation->reference_number }} &bull; {{ $reservation->check_in_date->format('F j, Y') }} to {{ $reservation->check_out_date->format('F j, Y') }}</p>
        </div>

        @include('guest.partials.flash-messages', [
            'wrap' => false,
            'containerClass' => 'space-y-3',
        ])

        <form method="POST" action="{{ route('guest.account.feedback.store', $reservation, false) }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6" data-guest-validate novalidate>
            @csrf
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Your feedback helps improve the homestay experience and is reviewed internally by staff.
            </div>

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    Please review the highlighted fields and try again.
                </div>
            @endif

            @php
                $ratings = [
                    'overall_rating' => ['label' => 'Overall Rating', 'required' => true],
                    'cleanliness_rating' => ['label' => 'Cleanliness', 'required' => false],
                    'comfort_rating' => ['label' => 'Comfort', 'required' => false],
                    'service_rating' => ['label' => 'Staff / Service', 'required' => false],
                    'value_rating' => ['label' => 'Value', 'required' => false],
                    'booking_experience_rating' => ['label' => 'Booking Experience', 'required' => false],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach($ratings as $field => $config)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $config['label'] }} @if($config['required']) * @endif</label>
                        <select id="{{ $field }}" name="{{ $field }}" @required($config['required']) class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                            <option value="">{{ $config['required'] ? 'Select rating...' : 'Optional rating...' }}</option>
                            @for($rating = 5; $rating >= 1; $rating--)
                                <option value="{{ $rating }}" @selected((string) old($field) === (string) $rating)>{{ $rating }} - {{ ['1' => 'Poor', '2' => 'Fair', '3' => 'Good', '4' => 'Very Good', '5' => 'Excellent'][(string) $rating] }}</option>
                            @endfor
                        </select>
                        @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <div>
                    <label for="would_stay_again" class="block text-sm font-medium text-gray-700 mb-1">Would you stay again?</label>
                    <select id="would_stay_again" name="would_stay_again" class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        <option value="">Prefer not to say</option>
                        <option value="1" @selected(old('would_stay_again') === '1')>Yes</option>
                        <option value="0" @selected(old('would_stay_again') === '0')>No</option>
                    </select>
                    @error('would_stay_again') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="comments" class="block text-sm font-medium text-gray-700 mb-1">Comments</label>
                <textarea id="comments" name="comments" rows="5" maxlength="2000" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]" placeholder="Share anything that would help us improve future stays.">{{ old('comments') }}</textarea>
                @error('comments') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('guest.account.reservations.show', $reservation, false) }}" class="inline-flex rounded-lg bg-gray-200 px-5 py-3 font-bold text-gray-800">Cancel</a>
                <button class="rounded-lg bg-[#00491E] px-5 py-3 font-bold text-white hover:bg-[#02681E]">Submit Feedback</button>
            </div>
        </form>
    </section>
@endsection
