@php
    $supportEmail = trim((string) \App\Support\GuestSiteSettings::get('guest_footer_email'));
    $supportPhone = trim((string) \App\Support\GuestSiteSettings::get('guest_footer_phone'));
    $supportPhoneHref = preg_replace('/[^+\d]/', '', $supportPhone);
@endphp

@if($supportEmail !== '' || $supportPhone !== '')
    <div class="mt-8 text-sm text-gray-600">
        <p>{{ $prompt ?? 'Need help? Contact us at:' }}</p>
        <p class="font-medium text-gray-900 mt-1">
            @if($supportEmail !== '')
                <span>Email: <a href="mailto:{{ $supportEmail }}" class="text-[#00491E] hover:underline">{{ $supportEmail }}</a></span>
            @endif
            @if($supportEmail !== '' && $supportPhone !== '')
                <span aria-hidden="true"> | </span>
            @endif
            @if($supportPhone !== '')
                <span>Phone: <a href="tel:{{ $supportPhoneHref }}" class="text-[#00491E] hover:underline">{{ $supportPhone }}</a></span>
            @endif
        </p>
    </div>
@endif
