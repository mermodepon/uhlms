@extends('layouts.guest')

@section('title', 'Make a Reservation')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Make a Reservation</h1>
            <p class="text-gray-200">Fill out the form below to request a reservation. Our staff will review and respond to your request.</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <form action="{{ route('guest.reserve.submit', [], false) }}" method="POST" class="space-y-8">
            @csrf
            @honeypot

            {{-- Guest Info --}}
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-[#00491E] mb-4">Personal Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="guest_last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                        <input type="text" name="guest_last_name" id="guest_last_name" value="{{ old('guest_last_name') }}" required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                        <input type="text" name="guest_first_name" id="guest_first_name" value="{{ old('guest_first_name') }}" required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_middle_initial" class="block text-sm font-medium text-gray-700 mb-1">Middle Initial</label>
                        <input type="text" name="guest_middle_initial" id="guest_middle_initial" value="{{ old('guest_middle_initial') }}" 
                               maxlength="10"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_middle_initial') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                        <input type="email" name="guest_email" id="guest_email" value="{{ old('guest_email') }}" required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="tel" name="guest_phone" id="guest_phone" value="{{ old('guest_phone') }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_age" class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                        <input type="number" name="guest_age" id="guest_age" value="{{ old('guest_age') }}"
                               min="1" max="120"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_age') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_gender" class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                        <select name="guest_gender" id="guest_gender" required
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                            <option value="">Select gender...</option>
                            <option value="Male" {{ old('guest_gender') == 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ old('guest_gender') == 'Female' ? 'selected' : '' }}>Female</option>
                            <option value="Other" {{ old('guest_gender') == 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('guest_gender') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="guest_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="guest_address" id="guest_address" rows="2"
                                  class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">{{ old('guest_address') }}</textarea>
                        @error('guest_address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Reservation Details --}}
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-[#00491E] mb-4">Reservation Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="preferred_room_type_id" class="block text-sm font-medium text-gray-700 mb-1">Preferred Room Type *</label>
                        <select name="preferred_room_type_id" id="preferred_room_type_id" required
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                            <option value="">Select a room type...</option>
                            @foreach($roomTypes as $rt)
                                @php
                                    $availabilityText = $rt->room_sharing_type === 'public' 
                                        ? "{$rt->available_beds} beds available"
                                        : "{$rt->available_rooms_count} rooms available";
                                    
                                    $displayText = "{$rt->name} - {$rt->getFormattedPrice()} ({$availabilityText}, Up to {$rt->capacity} guests)";
                                @endphp
                                <option value="{{ $rt->id }}" {{ old('preferred_room_type_id', request('room_type')) == $rt->id ? 'selected' : '' }}>
                                    {{ $displayText }}
                                </option>
                            @endforeach
                        </select>
                        @error('preferred_room_type_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="check_in_date" class="block text-sm font-medium text-gray-700 mb-1">Check-in Date *</label>
                        <input type="date" name="check_in_date" id="check_in_date" value="{{ old('check_in_date') }}" required
                               min="{{ date('Y-m-d') }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('check_in_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="check_out_date" class="block text-sm font-medium text-gray-700 mb-1">Check-out Date *</label>
                        <input type="date" name="check_out_date" id="check_out_date" value="{{ old('check_out_date') }}" required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('check_out_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="number_of_occupants" class="block text-sm font-medium text-gray-700 mb-1">Number of Occupants *</label>
                        <input type="number" name="number_of_occupants" id="number_of_occupants" value="{{ old('number_of_occupants', 1) }}" required
                               min="1" max="20"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('number_of_occupants') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose of Stay</label>
                        <select name="purpose" id="purpose"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                            <option value="">Select purpose...</option>
                            <option value="academic" {{ old('purpose') === 'academic' ? 'selected' : '' }}>Academic</option>
                            <option value="official" {{ old('purpose') === 'official' ? 'selected' : '' }}>Official Business</option>
                            <option value="personal" {{ old('purpose') === 'personal' ? 'selected' : '' }}>Personal</option>
                            <option value="event" {{ old('purpose') === 'event' ? 'selected' : '' }}>Event / Conference</option>
                            <option value="other" {{ old('purpose') === 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('purpose') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="special_requests" class="block text-sm font-medium text-gray-700 mb-1">Special Requests</label>
                        <textarea name="special_requests" id="special_requests" rows="3"
                                  placeholder="Any special requirements or requests..."
                                  class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">{{ old('special_requests') }}</textarea>
                        @error('special_requests') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Discount Declaration --}}
                    <div class="md:col-span-2 bg-blue-50 border-2 border-blue-200 rounded-xl p-6" x-data="{ discountDeclared: {{ old('discount_declared') ? 'true' : 'false' }} }">
                        <div class="flex items-start gap-3 mb-4">
                            <input type="checkbox" 
                                   name="discount_declared" 
                                   id="discount_declared" 
                                   value="1"
                                   x-model="discountDeclared"
                                   {{ old('discount_declared') ? 'checked' : '' }}
                                   class="w-5 h-5 text-[#00491E] focus:ring-[#00491E] mt-1 rounded">
                            <div class="flex-1">
                                <label for="discount_declared" class="block text-base font-semibold text-gray-900 cursor-pointer">
                                    I am eligible for a discount (PWD / Senior Citizen / Student)
                                </label>
                                <p class="text-sm text-gray-600 mt-1">
                                    If you qualify for a discount, you can only pay a deposit now. The remaining balance (with discount applied) will be due at check-in upon ID verification.
                                </p>
                            </div>
                        </div>

                        <div x-show="discountDeclared" x-transition class="mt-4">
                            <label for="discount_declared_type" class="block text-sm font-medium text-gray-700 mb-1">
                                Discount Type <span class="text-red-500">*</span>
                            </label>
                            <select name="discount_declared_type" 
                                    id="discount_declared_type"
                                    :required="discountDeclared"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                                <option value="">Select discount type...</option>
                                <option value="senior_citizen" {{ old('discount_declared_type') === 'senior_citizen' ? 'selected' : '' }}>Senior Citizen (20% discount)</option>
                                <option value="pwd" {{ old('discount_declared_type') === 'pwd' ? 'selected' : '' }}>PWD - Person with Disability (20% discount)</option>
                                <option value="student" {{ old('discount_declared_type') === 'student' ? 'selected' : '' }}>Student (10% discount)</option>
                            </select>
                            @error('discount_declared_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            
                            <div class="mt-3 bg-yellow-50 border-l-4 border-yellow-400 p-3 rounded">
                                <p class="text-sm text-yellow-800">
                                    <strong>⚠️ Important:</strong> You must present a valid ID at check-in to verify your discount eligibility. If you cannot provide valid proof, you will be charged the full undiscounted balance.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex justify-between items-center">
                <a href="{{ route('guest.rooms', [], false) }}" class="text-gray-500 hover:text-[#00491E] transition">
                    ← Back to Rooms
                </a>
                <button type="submit" class="bg-[#FFC600] text-[#00491E] px-8 py-3 rounded-lg font-bold text-lg hover:bg-yellow-400 transition shadow-lg">
                    Submit Reservation
                </button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
<script>
    document.getElementById('check_in_date').addEventListener('change', function() {
        const checkOut = document.getElementById('check_out_date');
        if (this.value) {
            const nextDay = new Date(this.value);
            nextDay.setDate(nextDay.getDate() + 1);
            checkOut.min = nextDay.toISOString().split('T')[0];
            if (checkOut.value && checkOut.value <= this.value) {
                checkOut.value = nextDay.toISOString().split('T')[0];
            }
        }
    });
</script>
@endpush
