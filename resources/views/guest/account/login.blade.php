@extends('layouts.guest')

@section('title', 'Guest Login')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Guest Login</h1>
            <p class="text-gray-200">Access your reservations and reuse your profile details for future stays.</p>
        </div>
    </section>

    <section class="max-w-md mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @include('guest.partials.flash-messages', [
            'wrap' => false,
            'containerClass' => 'mb-6 space-y-3',
        ])

        <form method="POST" action="{{ route('guest.account.login.submit', [], false) }}" class="bg-white rounded-xl shadow-md p-6 space-y-5" data-guest-validate novalidate>
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input id="password" name="password" type="password" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-[#00491E]">
                Remember me
            </label>
            <button class="w-full rounded-lg bg-[#00491E] px-5 py-3 font-bold text-white hover:bg-[#02681E]">Log In</button>
            <div class="flex flex-col gap-2 text-center text-sm">
                <a href="{{ route('guest.account.password.request', [], false) }}" class="font-semibold text-[#00491E] hover:underline">Forgot password?</a>
                <a href="{{ route('guest.account.register', [], false) }}" class="font-semibold text-[#00491E] hover:underline">Create a guest account</a>
            </div>
        </form>
    </section>
@endsection
