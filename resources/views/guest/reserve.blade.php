@extends('layouts.guest')

@section('title', 'Request a Reservation')

@section('content')
    @php
        $guestDateDefaults = \App\Support\GuestDatePolicy::defaults(
            old('check_in_date', request('check_in')),
            old('check_out_date', request('check_out')),
            old('check_in_date') !== null
        );
        $defaultCheckIn = $guestDateDefaults['check_in'];
        $defaultCheckOut = $guestDateDefaults['check_out'];
        $defaultMinCheckIn = $guestDateDefaults['min_check_in'];
        $defaultMinCheckOut = $guestDateDefaults['min_check_out'];
    @endphp
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Request a Reservation</h1>
            <p class="text-gray-200">Fill out the form below to submit a reservation request. Room assignment and confirmation happen after staff review.</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <form action="{{ route('guest.reserve.submit', [], false) }}" method="POST" class="space-y-8" data-guest-validate novalidate>
            @csrf
            @honeypot

            {{-- Guest Info --}}
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-[#00491E] mb-4">Personal Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="guest_last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                        <input type="text" name="guest_last_name" id="guest_last_name" value="{{ old('guest_last_name') }}" required maxlength="255"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                        <input type="text" name="guest_first_name" id="guest_first_name" value="{{ old('guest_first_name') }}" required maxlength="255"
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
                        <input type="email" name="guest_email" id="guest_email" value="{{ old('guest_email') }}" required maxlength="255"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_phone" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                        <input type="tel" name="guest_phone" id="guest_phone" value="{{ old('guest_phone') }}" maxlength="20"
                               required
                               pattern="^(09[0-9]{9}|\+639[0-9]{9}|639[0-9]{9})$"
                               data-validation-pattern-message="Enter a valid Philippine mobile number, e.g. 09171234567 or +639171234567."
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_age" class="block text-sm font-medium text-gray-700 mb-1">Age *</label>
                        <input type="number" name="guest_age" id="guest_age" value="{{ old('guest_age') }}" data-integer="true" step="1"
                               required min="18" max="120" data-validation-min-message="Guest age must be at least 18."
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('guest_age') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="guest_gender" class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                        <select name="guest_gender" id="guest_gender" required
                                class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                            <option value="">Select gender...</option>
                            <option value="Male" {{ old('guest_gender') == 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ old('guest_gender') == 'Female' ? 'selected' : '' }}>Female</option>
                            <option value="Other" {{ old('guest_gender') == 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('guest_gender') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="guest_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="guest_address" id="guest_address" rows="2" maxlength="1000"
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
                                class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                            <option value="">Select a room type...</option>
                            @foreach($roomTypes as $rt)
                                @php
                                    $availabilityText = $rt->room_sharing_type === 'public' 
                                        ? ($rt->availability_label ?? (($rt->available_beds_count ?? 0) . ' beds available'))
                                        : ($rt->availability_label ?? "{$rt->available_rooms_count} rooms available");
                                    
                                    $displayText = "{$rt->name} - {$rt->getFormattedPrice()} ({$availabilityText}, Up to {$rt->capacity} guests)";
                                @endphp
                                <option value="{{ $rt->id }}"
                                        data-room-sharing-type="{{ $rt->room_sharing_type }}"
                                        data-capacity="{{ (int) $rt->capacity }}"
                                        data-available-beds="{{ $rt->available_beds_count ?? '' }}"
                                        data-available-rooms="{{ $rt->available_rooms_count ?? '' }}"
                                        {{ old('preferred_room_type_id', request('room_type')) == $rt->id ? 'selected' : '' }}>
                                    {{ $displayText }}
                                </option>
                            @endforeach
                        </select>
                        @error('preferred_room_type_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="check_in_date" class="block text-sm font-medium text-gray-700 mb-1">Check-in Date *</label>
                        <input type="date" name="check_in_date" id="check_in_date" value="{{ $defaultCheckIn }}" required
                               min="{{ $defaultMinCheckIn }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('check_in_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="check_out_date" class="block text-sm font-medium text-gray-700 mb-1">Check-out Date *</label>
                        <input type="date" name="check_out_date" id="check_out_date" value="{{ $defaultCheckOut }}" required
                               min="{{ $defaultMinCheckOut }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('check_out_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="requested_room_count" class="block text-sm font-medium text-gray-700 mb-1">Rooms Requested *</label>
                        <input type="number" name="requested_room_count" id="requested_room_count" value="{{ old('requested_room_count', 1) }}" required data-integer="true" step="1"
                               min="1" max="20"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('requested_room_count') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="number_of_occupants" class="block text-sm font-medium text-gray-700 mb-1">Number of Occupants *</label>
                        <input type="number" name="number_of_occupants" id="number_of_occupants" value="{{ old('number_of_occupants', request('guests', 1)) }}" required data-integer="true" step="1"
                               min="1" max="20" data-dynamic-max="20"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('number_of_occupants') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose of Stay</label>
                        <select name="purpose" id="purpose"
                                class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
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
                        <textarea name="special_requests" id="special_requests" rows="3" maxlength="2000"
                                  placeholder="Any special requirements or requests..."
                                  class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">{{ old('special_requests') }}</textarea>
                        @error('special_requests') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Additional Room Types</p>
                                <p class="text-sm text-gray-600">Use this when one reservation needs more than one room type.</p>
                            </div>
                            <button type="button" id="add-room-request" class="inline-flex items-center justify-center rounded-lg border border-[#00491E] px-4 py-2 text-sm font-semibold text-[#00491E] hover:bg-[#00491E] hover:text-white transition">
                                Add another room type
                            </button>
                        </div>
                        <div id="additional-room-requests" class="mt-4 space-y-4"></div>
                    </div>
                    <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <label for="availability_acknowledged" class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox"
                                   name="availability_acknowledged"
                                   id="availability_acknowledged"
                                   value="1"
                                   {{ old('availability_acknowledged') ? 'checked' : '' }}
                                   class="mt-1 w-5 h-5 flex-shrink-0 rounded border-gray-300 text-[#00491E] focus:ring-[#00491E]">
                            <span>
                                <span class="block text-sm font-semibold text-amber-900">Submit even if availability looks limited</span>
                                <span class="block text-sm text-amber-800">If your selected room type appears unavailable for those dates, you can still send a reservation request for staff review.</span>
                            </span>
                        </label>
                        @error('availability_acknowledged') <p class="text-red-500 text-xs mt-3">{{ $message }}</p> @enderror
                    </div>

                    {{-- Discount Declaration --}}
                    <div class="md:col-span-2 bg-blue-50 border border-blue-200 rounded-xl p-4" x-data="{ discountDeclared: {{ old('discount_declared') ? 'true' : 'false' }} }">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" 
                                   name="discount_declared" 
                                   id="discount_declared" 
                                   value="1"
                                   x-model="discountDeclared"
                                   {{ old('discount_declared') ? 'checked' : '' }}
                                   class="w-5 h-5 flex-shrink-0 text-[#00491E] focus:ring-[#00491E] mt-1 rounded border-gray-300">
                            <div class="flex-1">
                                <label for="discount_declared" class="block text-sm font-semibold text-gray-900 cursor-pointer">
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
                                    class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
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
                    Submit Reservation Request
                </button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
<script>
    const checkInDateInput = document.getElementById('check_in_date');
    const checkOutDateInput = document.getElementById('check_out_date');
    const roomTypeInput = document.getElementById('preferred_room_type_id');
    const requestedRoomCountInput = document.getElementById('requested_room_count');
    const occupantsInput = document.getElementById('number_of_occupants');
    const reservationForm = document.querySelector('form[data-guest-validate]');
    const additionalRoomRequests = document.getElementById('additional-room-requests');
    const addRoomRequestButton = document.getElementById('add-room-request');
    const roomTypeAvailabilityUrlTemplate = @json(route('api.tour.room-type-availability', ['id' => '__ROOM_TYPE_ID__'], false));

    if (checkInDateInput && checkOutDateInput) {
        const toDateString = (date) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const addDays = (dateString, days) => {
            const [year, month, day] = dateString.split('-').map(Number);
            const date = new Date(year, month - 1, day);
            date.setDate(date.getDate() + days);
            return toDateString(date);
        };

        const syncCheckoutMinimum = () => {
            if (!checkInDateInput.value) return;

            const minCheckOut = addDays(checkInDateInput.value, 1);
            checkOutDateInput.min = minCheckOut;

            if (!checkOutDateInput.value || checkOutDateInput.value <= checkInDateInput.value) {
                checkOutDateInput.value = minCheckOut;
            }
        };

        if (!checkInDateInput.value || checkInDateInput.value < checkInDateInput.min) {
            checkInDateInput.value = checkInDateInput.min;
        }
        syncCheckoutMinimum();

        checkInDateInput.addEventListener('change', syncCheckoutMinimum);
    }

    if (roomTypeInput && occupantsInput && checkInDateInput && checkOutDateInput) {
        let activeAvailabilityRequest = 0;

        const currentRoomTypeOption = () => roomTypeInput.options[roomTypeInput.selectedIndex] || null;

        const occupantLimitMessage = (max, isPrivate) => {
            if (isPrivate) return `This room type allows up to ${max} occupants.`;
            return max > 0
                ? `Only ${max} beds are available for these dates.`
                : 'No beds are available for these dates.';
        };

        const selectedRoomCount = () => Math.max(1, Number(requestedRoomCountInput?.value || 1));

        const applyOccupantLimit = (max, isPrivate) => {
            const dynamicMax = Number.isFinite(Number(max)) ? Number(max) : 20;
            const htmlMax = Math.max(1, dynamicMax);
            occupantsInput.max = String(htmlMax);
            occupantsInput.dataset.dynamicMax = String(dynamicMax);
            occupantsInput.dataset.validationMaxMessage = occupantLimitMessage(dynamicMax, isPrivate);

            if (dynamicMax >= 1 && Number(occupantsInput.value || 0) > dynamicMax) {
                occupantsInput.value = String(dynamicMax);
            }

            if (window.GuestRealtimeValidation && reservationForm) {
                window.GuestRealtimeValidation.validateField(occupantsInput, reservationForm, true);
            }
        };

        const applySelectedRoomFallbackLimit = () => {
            const selectedOption = currentRoomTypeOption();
            if (!selectedOption || !selectedOption.value) {
                applyOccupantLimit(20, true);
                return;
            }

            const isPrivate = selectedOption.dataset.roomSharingType !== 'public';
            const fallbackMax = isPrivate
                ? Number(selectedOption.dataset.capacity || 20) * selectedRoomCount()
                : Number(selectedOption.dataset.availableBeds || 20);
            applyOccupantLimit(fallbackMax, isPrivate);
        };

        const syncOccupantLimit = async () => {
            const selectedOption = currentRoomTypeOption();
            if (!selectedOption || !selectedOption.value) {
                applyOccupantLimit(20, true);
                return;
            }

            applySelectedRoomFallbackLimit();

            const requestId = ++activeAvailabilityRequest;
            const url = new URL(
                roomTypeAvailabilityUrlTemplate.replace('__ROOM_TYPE_ID__', encodeURIComponent(selectedOption.value)),
                window.location.href
            );
            url.searchParams.set('check_in', checkInDateInput.value);
            url.searchParams.set('check_out', checkOutDateInput.value);
            url.searchParams.set('guests', occupantsInput.value || '1');

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const payload = await response.json();
                if (requestId !== activeAvailabilityRequest || !payload?.success || !payload.data) return;

                const data = payload.data;
                const isPrivate = Boolean(data.is_private);
                const max = isPrivate
                    ? Number(data.capacity || selectedOption.dataset.capacity || 20) * selectedRoomCount()
                    : Number(data.available_beds_count ?? 0);
                applyOccupantLimit(max, isPrivate);
            } catch (error) {
                applySelectedRoomFallbackLimit();
            }
        };

        roomTypeInput.addEventListener('change', syncOccupantLimit);
        requestedRoomCountInput?.addEventListener('input', syncOccupantLimit);
        checkInDateInput.addEventListener('change', syncOccupantLimit);
        checkOutDateInput.addEventListener('change', syncOccupantLimit);
        occupantsInput.addEventListener('input', () => {
            if (window.GuestRealtimeValidation && reservationForm) {
                window.GuestRealtimeValidation.validateField(occupantsInput, reservationForm);
            }
        });

        syncOccupantLimit();
    }

    if (additionalRoomRequests && addRoomRequestButton && roomTypeInput && checkInDateInput && checkOutDateInput) {
        let roomRequestIndex = 0;

        const escapeHtml = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const roomTypeOptionsHtml = () => Array.from(roomTypeInput.options)
            .map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.textContent)}</option>`)
            .join('');

        const refreshExtraAvailability = async (row) => {
            const select = row.querySelector('[data-extra-room-type]');
            const countInput = row.querySelector('[data-extra-room-count]');
            const occupants = row.querySelector('[data-extra-occupants]');
            const message = row.querySelector('[data-extra-availability]');
            const selected = select?.options[select.selectedIndex];

            if (!select?.value || !message) {
                if (message) message.textContent = '';
                return;
            }

            const url = new URL(
                roomTypeAvailabilityUrlTemplate.replace('__ROOM_TYPE_ID__', encodeURIComponent(select.value)),
                window.location.href
            );
            url.searchParams.set('check_in', checkInDateInput.value);
            url.searchParams.set('check_out', checkOutDateInput.value);
            url.searchParams.set('guests', occupants?.value || '1');

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const payload = await response.json();
                const data = payload?.data;
                if (!payload?.success || !data) return;

                const roomCount = Math.max(1, Number(countInput?.value || 1));
                const isPrivate = Boolean(data.is_private);
                const maxGuests = isPrivate
                    ? Number(data.capacity || selected?.dataset?.capacity || 20) * roomCount
                    : Number(data.available_beds_count ?? 0);

                if (occupants) {
                    occupants.max = String(Math.max(1, maxGuests));
                    occupants.dataset.validationMaxMessage = isPrivate
                        ? `This request allows up to ${maxGuests} occupants across ${roomCount} room(s).`
                        : `Only ${maxGuests} beds are available for these dates.`;
                }

                message.textContent = data.availability_label || '';
            } catch (error) {
                message.textContent = '';
            }
        };

        const addRoomRequestRow = () => {
            const index = roomRequestIndex++;
            const row = document.createElement('div');
            row.className = 'rounded-lg border border-gray-200 bg-white p-4';
            row.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room Type</label>
                        <select name="room_requests[${index}][room_type_id]" data-extra-room-type class="guest-select w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                            ${roomTypeOptionsHtml()}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rooms</label>
                        <input type="number" name="room_requests[${index}][requested_room_count]" data-extra-room-count value="1" min="1" max="20" step="1" data-integer="true" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Occupants</label>
                        <input type="number" name="room_requests[${index}][occupant_count]" data-extra-occupants value="1" min="1" max="200" step="1" data-integer="true" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <input type="text" name="room_requests[${index}][notes]" maxlength="500" placeholder="Optional note for staff" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500" data-extra-availability></p>
                    <button type="button" class="text-sm font-semibold text-red-600 hover:text-red-700" data-remove-room-request>Remove</button>
                </div>
            `;

            additionalRoomRequests.appendChild(row);
            row.querySelector('[data-extra-room-type]')?.addEventListener('change', () => refreshExtraAvailability(row));
            row.querySelector('[data-extra-room-count]')?.addEventListener('input', () => refreshExtraAvailability(row));
            row.querySelector('[data-extra-occupants]')?.addEventListener('input', () => refreshExtraAvailability(row));
            row.querySelector('[data-remove-room-request]')?.addEventListener('click', () => row.remove());
            refreshExtraAvailability(row);
        };

        addRoomRequestButton.addEventListener('click', addRoomRequestRow);
    }
</script>
@endpush
