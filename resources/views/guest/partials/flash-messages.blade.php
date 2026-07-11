@php
    $wrap = $wrap ?? true;
    $containerClass = $containerClass ?? 'mx-auto max-w-7xl space-y-3 px-4 pb-8 pt-6 sm:px-6 lg:px-8';
@endphp

@if(session('success') || session('guest_account_prompt'))
    @if($wrap)
        <div class="bg-gray-50">
    @endif
            <div class="{{ $containerClass }}">
                @if(session('success'))
                    <div class="rounded-lg border border-green-300 bg-green-50 px-5 py-4 text-green-800 shadow-sm" role="alert">
                        <span class="block leading-relaxed">{{ session('success') }}</span>
                    </div>
                @endif
                @if(session('guest_account_prompt'))
                    <div class="flex flex-col gap-3 rounded-lg border border-blue-300 bg-blue-50 px-5 py-4 text-blue-900 shadow-sm sm:flex-row sm:items-center sm:justify-between" role="status">
                        <span class="leading-relaxed">Create a guest account to save your details and view reservation history faster next time.</span>
                        <a href="{{ route('guest.account.register', [], false) }}" class="rounded-lg bg-[#00491E] px-4 py-2 text-center font-semibold text-white">Create Account</a>
                    </div>
                @endif
            </div>
    @if($wrap)
        </div>
    @endif
@endif
