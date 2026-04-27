@extends('layouts.guest')

@section('title', 'Complete Payment')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold mb-2">Complete Your Payment</h1>
            <p class="text-gray-200">Secure online payment for your reservation</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {{-- Status-based Message --}}
        @if($reservation->status === 'pending')
            <div class="bg-amber-50 border-l-4 border-amber-500 rounded-lg p-5 mb-6">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-amber-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <h3 class="text-lg font-semibold text-amber-900 mb-1">Reservation Under Review</h3>
                        <p class="text-amber-800 text-sm leading-relaxed">
                            Your reservation is currently being reviewed by our staff. You can proceed with payment now to <strong>expedite confirmation</strong> — 
                            paying in advance will automatically confirm your reservation once processed.
                        </p>
                    </div>
                </div>
            </div>
        @elseif($reservation->status === 'approved')
            <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-5 mb-6">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-green-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-green-900 mb-1">✅ Reservation Approved!</h3>
                        <p class="text-green-800 text-sm leading-relaxed mb-2">
                            Great news! Your reservation has been approved. Complete your payment below to <strong>confirm your reservation</strong>.
                        </p>
                        @if($reservation->approved_at)
                            @php
                                $paymentDeadline = $reservation->approved_at->addHours(72);
                                $hoursRemaining = now()->diffInHours($paymentDeadline, false);
                            @endphp
                            @if($hoursRemaining > 0)
                                <div class="bg-white border border-green-200 rounded-lg p-3 mt-2">
                                    <p class="text-sm text-gray-700">
                                        <svg class="w-4 h-4 inline text-amber-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                        </svg>
                                        <strong>Payment Deadline:</strong> {{ $paymentDeadline->format('F j, Y g:i A') }} 
                                        <span class="text-amber-700">({{ abs($hoursRemaining) }} hours remaining)</span>
                                    </p>
                                    <p class="text-xs text-gray-600 mt-1">
                                        ⚠️ Unpaid reservations will be automatically cancelled after 72 hours.
                                    </p>
                                </div>
                            @else
                                <div class="bg-red-100 border border-red-300 rounded-lg p-3 mt-2">
                                    <p class="text-sm text-red-800">
                                        <svg class="w-4 h-4 inline text-red-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        <strong>Payment deadline has passed!</strong> This reservation may be cancelled. Please complete payment immediately.
                                    </p>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @elseif($reservation->status === 'confirmed')
            <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-5 mb-6">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-blue-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-900 mb-1">🎉 Reservation Confirmed!</h3>
                        <p class="text-blue-800 text-sm">
                            Your deposit has been received and your reservation is confirmed. We look forward to welcoming you!
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Reservation Summary --}}
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-[#00491E] mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Reservation Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-600 font-medium">Reservation Number</p>
                    <p class="text-gray-900 text-lg font-bold">{{ $reservation->reference_number }}</p>
                </div>
                <div>
                    <p class="text-gray-600 font-medium">Guest Name</p>
                    <p class="text-gray-900">{{ $reservation->guest_name }}</p>
                </div>
                <div>
                    <p class="text-gray-600 font-medium">Check-in Date</p>
                    <p class="text-gray-900">{{ $reservation->check_in_date->format('F j, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-600 font-medium">Check-out Date</p>
                    <p class="text-gray-900">{{ $reservation->check_out_date->format('F j, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-600 font-medium">Number of Guests</p>
                    <p class="text-gray-900">{{ $reservation->number_of_occupants }} {{ Str::plural('guest', $reservation->number_of_occupants) }}</p>
                </div>
                <div>
                    <p class="text-gray-600 font-medium">Room Type</p>
                    <p class="text-gray-900">{{ $reservation->preferredRoomType?->name ?? 'To be assigned' }}</p>
                </div>
            </div>
        </div>

        {{-- Payment Form --}}
        <form action="{{ route('guest.payment.initialize', ['token' => $reservation->payment_link_token], false) }}" method="POST" class="space-y-6" x-data="{ paymentType: 'deposit' }">
            @csrf

            {{-- Discount Declaration Notice --}}
            @if($reservation->discount_declared)
                <div class="bg-yellow-50 border-l-4 border-yellow-400 rounded-lg p-5 shadow-md">
                    <div class="flex items-start">
                        <svg class="w-6 h-6 text-yellow-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <h3 class="text-yellow-900 font-bold text-lg mb-1">Discount Holder - Deposit Payment Only</h3>
                            <p class="text-yellow-800 text-sm mb-2">
                                You declared eligibility for a <strong>{{ ucwords(str_replace('_', ' ', $reservation->discount_declared_type)) }} discount</strong>. 
                                You can only pay a deposit now to secure your reservation.
                            </p>
                            <p class="text-yellow-800 text-sm">
                                <strong>Upon check-in:</strong> Present your valid ID to verify your discount eligibility. 
                                Your discounted balance will be calculated and collected at that time.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Payment Amount and Type Selection --}}
            <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-blue-900 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Choose Payment Option
                </h2>
                @php
                    $totalCharges = $reservation->balance_due + $reservation->payments_total;
                    $isEstimated = $totalCharges <= 0;
                    
                    // Calculate amounts
                    $fullAmount = $fullAmount ?? $reservation->calculateFullAmount();
                    $depositAmount = $depositAmount ?? $reservation->calculateDepositAmount();
                @endphp

            {{-- Payment Type Selection --}}
            <div class="space-y-4 mb-6">
                {{-- Deposit Option --}}
                <div @click="paymentType = 'deposit'" 
                     class="border-2 rounded-lg p-5 transition-all cursor-pointer"
                     :class="paymentType === 'deposit' ? 'border-blue-500 bg-blue-50 shadow-md' : 'border-gray-300 hover:border-blue-300'">
                    <input type="radio" name="payment_type" value="deposit" 
                           class="sr-only" 
                           x-model="paymentType"
                           checked>
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center mr-3 transition-all"
                                     :class="paymentType === 'deposit' ? 'border-blue-500 bg-blue-500' : 'border-gray-400'">
                                    <svg class="w-3 h-3 text-white transition-opacity" 
                                         :class="paymentType === 'deposit' ? 'opacity-100' : 'opacity-0'"
                                         fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900">Pay Deposit ({{ number_format($depositPercentage ?? 30, 0) }}%)</h3>
                            </div>
                            <p class="text-sm text-gray-600 ml-8 mb-2">
                                • Secure your reservation with a deposit<br>
                                • Pay remaining balance upon check-in<br>
                                • Lower upfront cost
                            </p>
                            <div class="ml-8 bg-white border border-blue-200 rounded-lg px-3 py-2 inline-block">
                                <span class="text-2xl font-bold text-blue-900">₱{{ number_format($depositAmount, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!$reservation->discount_declared)
                {{-- Full Payment Option --}}
                <div @click="paymentType = 'full'" 
                     class="border-2 rounded-lg p-5 transition-all cursor-pointer"
                     :class="paymentType === 'full' ? 'border-green-500 bg-green-50 shadow-md' : 'border-gray-300 hover:border-green-300'">
                    <input type="radio" name="payment_type" value="full" 
                           class="sr-only"
                           x-model="paymentType">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center mr-3 transition-all"
                                     :class="paymentType === 'full' ? 'border-green-500 bg-green-500' : 'border-gray-400'">
                                    <svg class="w-3 h-3 text-white transition-opacity" 
                                         :class="paymentType === 'full' ? 'opacity-100' : 'opacity-0'"
                                         fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900">Pay Full Amount</h3>
                                <span class="ml-2 px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">RECOMMENDED</span>
                            </div>
                            <p class="text-sm text-gray-600 ml-8 mb-2">
                                • No payment needed at check-in<br>
                                • Faster check-in process<br>
                                • Reservation fully secured
                            </p>
                            <div class="ml-8 bg-white border border-green-200 rounded-lg px-3 py-2 inline-block">
                                <span class="text-2xl font-bold text-green-900">₱{{ number_format($fullAmount, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Calculation Breakdown --}}
            @if($isEstimated && $fullAmount > 0)
                <div class="bg-white border border-blue-200 rounded-lg p-3 text-sm mb-4">
                    <p class="font-semibold text-gray-700 mb-2">Estimated Charges:</p>
                    <div class="text-xs text-gray-600 space-y-1">
                        <p>• {{ $reservation->preferredRoomType->name }}: ₱{{ number_format($reservation->preferredRoomType->base_rate, 2) }}{{ $reservation->preferredRoomType->pricing_type === 'per_person' ? '/person' : '' }}/night</p>
                        <p>• {{ $reservation->nights }} {{ Str::plural('night', $reservation->nights) }} × {{ $reservation->number_of_occupants }} {{ Str::plural('guest', $reservation->number_of_occupants) }}</p>
                        <p class="text-amber-600 font-medium mt-2">* Final amount will be confirmed upon check-in (add-ons, discounts, etc. may apply)</p>
                    </div>
                </div>
            @endif
            
            <div class="mt-4">
                <p class="text-sm text-gray-600 bg-white border border-blue-200 rounded-lg p-3" x-show="paymentType === 'deposit'">
                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    The remaining balance of <strong>₱{{ number_format($fullAmount - $depositAmount, 2) }}</strong> will be paid upon check-in at the homestay.
                </p>
                <p class="text-sm text-gray-600 bg-white border border-green-200 rounded-lg p-3" x-show="paymentType === 'full'">
                    <svg class="w-4 h-4 inline mr-1 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <strong>Full payment secured!</strong> No additional payment needed upon check-in. Just show your confirmation and ID.
                </p>
            </div>
        </div>

        {{-- Payment Method Selection --}}
        <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-[#00491E] mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Select Payment Method
                </h2>

                <div class="space-y-3">
                    {{-- GCash Option --}}
                    <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 transition-colors has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="payment_method" value="gcash" class="w-5 h-5 text-blue-600 focus:ring-blue-500" required>
                        <div class="ml-3 flex-1 flex items-center justify-between">
                            <span class="font-medium text-gray-900">GCash</span>
                            <svg class="h-6" viewBox="0 0 100 30" fill="none">
                                <rect width="100" height="30" rx="4" fill="#007DFF"/>
                                <text x="50" y="20" font-family="Arial" font-weight="bold" font-size="16" fill="white" text-anchor="middle">GCash</text>
                            </svg>
                        </div>
                    </label>

                    {{-- PayMaya Option --}}
                    <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 transition-colors has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="payment_method" value="paymaya" class="w-5 h-5 text-blue-600 focus:ring-blue-500" required>
                        <div class="ml-3 flex-1 flex items-center justify-between">
                            <span class="font-medium text-gray-900">Maya (PayMaya)</span>
                            <svg class="h-6" viewBox="0 0 100 30" fill="none">
                                <rect width="100" height="30" rx="4" fill="#4CAF50"/>
                                <text x="50" y="20" font-family="Arial" font-weight="bold" font-size="16" fill="white" text-anchor="middle">Maya</text>
                            </svg>
                        </div>
                    </label>

                    {{-- GrabPay Option --}}
                    <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 transition-colors has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="payment_method" value="grab_pay" class="w-5 h-5 text-blue-600 focus:ring-blue-500" required>
                        <div class="ml-3 flex-1 flex items-center justify-between">
                            <span class="font-medium text-gray-900">GrabPay</span>
                            <svg class="h-6" viewBox="0 0 100 30" fill="none">
                                <rect width="100" height="30" rx="4" fill="#00B14F"/>
                                <text x="50" y="20" font-family="Arial" font-weight="bold" font-size="14" fill="white" text-anchor="middle">GrabPay</text>
                            </svg>
                        </div>
                    </label>
                </div>

                @error('payment_method')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                @enderror
            </div>

            {{-- Terms and Conditions --}}
            <div class="bg-white rounded-xl shadow-md p-6">
                <label class="flex items-start">
                    <input type="checkbox" name="accept_terms" value="1" class="w-5 h-5 text-[#00491E] focus:ring-[#00491E] mt-1" required>
                    <span class="ml-3 text-sm text-gray-700">
                        <span x-show="paymentType === 'deposit'">
                            I understand that this is a deposit payment and the remaining balance will be paid upon check-in.
                        </span>
                        <span x-show="paymentType === 'full'">
                            I understand that this is a full payment and no additional payment is needed upon check-in.
                        </span>
                        I agree to the university homestay's payment and cancellation policies.
                    </span>
                </label>
                @error('accept_terms')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                @enderror
            </div>

            {{-- Error Messages --}}
            @if ($errors->any())
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm text-red-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Submit Button --}}
            @if($depositAmount > 0 && $fullAmount > 0)
                <button type="submit" class="w-full bg-gradient-to-r from-[#00491E] to-[#02681E] text-white py-4 px-6 rounded-lg font-bold text-lg hover:from-[#003817] hover:to-[#015717] transition-all shadow-lg hover:shadow-xl flex items-center justify-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Proceed to Secure Payment
                </button>
            @else
                <button type="button" disabled class="w-full bg-gray-300 text-gray-500 py-4 px-6 rounded-lg font-bold text-lg cursor-not-allowed flex items-center justify-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Payment Unavailable - Contact Homestay Office
                </button>
            @endif

            {{-- Security Badge --}}
            <div class="flex items-center justify-center space-x-4 text-sm text-gray-600">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    <span>SSL Encrypted</span>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Powered by PayMongo</span>
                </div>
            </div>
        </form>
    </section>
@endsection
