@extends('layouts.guest')

@section('title', 'Create Guest Account')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Create Guest Account</h1>
            <p class="text-gray-200">Save your details and make future reservations faster.</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @include('guest.partials.flash-messages', [
            'wrap' => false,
            'containerClass' => 'mb-6 space-y-3',
        ])

        <form method="POST" action="{{ route('guest.account.register.submit', [], false) }}" class="bg-white rounded-xl shadow-md p-6 space-y-6" data-guest-validate novalidate>
            @csrf
            @honeypot
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="255" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="255" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="middle_initial" class="block text-sm font-medium text-gray-700 mb-1">Middle Initial</label>
                    <input type="text" id="middle_initial" name="middle_initial" value="{{ old('middle_initial') }}" maxlength="10" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30"
                           pattern="^(09[0-9]{9}|\+639[0-9]{9}|639[0-9]{9})$"
                           data-validation-pattern-message="Enter a valid Philippine mobile number, e.g. 09171234567 or +639171234567."
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <select id="gender" name="gender" class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        <option value="">Select gender...</option>
                        @foreach(['Male', 'Female', 'Other'] as $gender)
                            <option value="{{ $gender }}" @selected(old('gender') === $gender)>{{ $gender }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="age" class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                    <input type="number" id="age" name="age" value="{{ old('age') }}" min="18" max="120" data-integer="true" step="1" data-validation-min-message="Guest age must be at least 18." class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    @error('age') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="address" name="address" rows="2" maxlength="1000" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">{{ old('address') }}</textarea>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                    <input type="password" id="password" name="password" required minlength="8" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                </div>
            </div>
            <button class="w-full rounded-lg bg-[#00491E] px-5 py-3 font-bold text-white hover:bg-[#02681E]">Create Account</button>
            <p class="text-center text-sm text-gray-600">Already have an account? <a href="{{ route('guest.account.login', [], false) }}" class="font-semibold text-[#00491E] hover:underline">Log in</a></p>
        </form>
    </section>
@endsection
