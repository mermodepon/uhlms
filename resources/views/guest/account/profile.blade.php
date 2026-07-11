@extends('layouts.guest')

@section('title', 'My Profile')
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h1 class="mb-6 text-3xl font-bold text-[#00491E]">My Profile</h1>
        @include('guest.partials.flash-messages', [
            'wrap' => false,
            'containerClass' => 'mb-6 space-y-3',
        ])

        <form method="POST" action="{{ route('guest.account.profile.update', [], false) }}" class="bg-white rounded-xl shadow-md p-6 space-y-5" data-guest-validate novalidate>
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $account->last_name) }}" required maxlength="255" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                </div>
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $account->first_name) }}" required maxlength="255" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                </div>
                <div>
                    <label for="middle_initial" class="block text-sm font-medium text-gray-700 mb-1">Middle Initial</label>
                    <input type="text" id="middle_initial" name="middle_initial" value="{{ old('middle_initial', $account->middle_initial) }}" maxlength="10" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $account->phone) }}" maxlength="30"
                           pattern="^(09[0-9]{9}|\+639[0-9]{9}|639[0-9]{9})$"
                           data-validation-pattern-message="Enter a valid Philippine mobile number, e.g. 09171234567 or +639171234567."
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                </div>
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <select id="gender" name="gender" class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        <option value="">Select gender...</option>
                        @foreach(['Male', 'Female', 'Other'] as $gender)
                            <option value="{{ $gender }}" @selected(old('gender', $account->gender) === $gender)>{{ $gender }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="age" class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                    <input type="number" id="age" name="age" value="{{ old('age', $account->age) }}" min="18" max="120" data-integer="true" step="1" data-validation-min-message="Guest age must be at least 18." class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                </div>
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="address" name="address" rows="3" maxlength="1000" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">{{ old('address', $account->address) }}</textarea>
                </div>
            </div>
            <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">Profile changes update your contact details and prefill future reservation forms. Past reservation records stay as originally submitted.</p>
            <button class="rounded-lg bg-[#00491E] px-5 py-3 font-bold text-white hover:bg-[#02681E]">Save Profile</button>
        </form>
    </section>
@endsection
