@extends('layouts.guest')

@section('title', 'Reset Password')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="max-w-md mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @include('guest.partials.flash-messages', [
            'wrap' => false,
            'containerClass' => 'mb-6 space-y-3',
        ])

        <form method="POST" action="{{ route('guest.account.password.update', [], false) }}" class="bg-white rounded-xl shadow-md p-6 space-y-5" data-guest-validate novalidate>
            @csrf
            <h1 class="text-2xl font-bold text-[#00491E]">Choose New Password</h1>
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" name="email" required value="{{ old('email', $email) }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input type="password" id="password" name="password" required minlength="8" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
            </div>
            <button class="w-full rounded-lg bg-[#00491E] px-5 py-3 font-bold text-white">Reset Password</button>
        </form>
    </section>
@endsection
