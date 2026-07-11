<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $guestSite = \App\Support\GuestSiteSettings::all();
        $siteTitle = $guestSite['guest_site_title'];
        $institutionName = $guestSite['guest_institution_name'];
        $brandName = $guestSite['guest_brand_name'];
        $logoSrc = \App\Support\GuestSiteSettings::logoUrl();
        $themeColor = $guestSite['guest_primary_accent_color'];
        $themeFont = $guestSite['guest_theme_font'];
        $maintenanceMode = $guestSite['guest_maintenance_enabled'];
        $maintenanceMessage = $guestSite['guest_maintenance_message'];
        $highContrast = $guestSite['guest_high_contrast'];
        $largeText = $guestSite['guest_large_text'];
        $showAnnouncement = $guestSite['guest_announcement_enabled'];
        $announcementText = $guestSite['guest_announcement_text'];
    @endphp
    <title>{{ trim($__env->yieldContent('title', '')) !== '' ? trim($__env->yieldContent('title')) . ' - ' . $siteTitle : $siteTitle }}</title>
    <link rel="icon" type="image/png" href="{{ $logoSrc }}">
    <link rel="apple-touch-icon" href="{{ $logoSrc }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --cmu-yellow: {{ $themeColor }};
            --cmu-green: #00491E;
            --cmu-green-alt1: #02681E;
            --cmu-green-alt2: #919F02;
            --guest-font-body: "Montserrat", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --guest-font-display: "Palatino Linotype", "Book Antiqua", Palatino, Georgia, serif;
        }
        .bg-\[\#FFC600\] { background-color: var(--cmu-yellow) !important; }
        .text-\[\#FFC600\] { color: var(--cmu-yellow) !important; }
        .border-\[\#FFC600\] { border-color: var(--cmu-yellow) !important; }
        .ring-\[\#FFC600\] { --tw-ring-color: var(--cmu-yellow) !important; }
        body {
            font-family: {{ $themeFont == 'serif' ? 'var(--guest-font-display)' : ($themeFont == 'mono' ? 'Menlo, Monaco, monospace' : 'var(--guest-font-body)') }};
        }
        h1, h2, h3, h4, h5, h6,
        .guest-display,
        .guest-brand {
            font-family: var(--guest-font-display);
        }
        button,
        input,
        select,
        textarea {
            font-family: inherit;
        }
        /* Improve form input visibility */
        input:not([type]),
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        input[type="date"],
        input[type="password"],
        input[type="search"],
        select,
        textarea {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
            border: 2px solid #d1d5db !important;
            background-color: #f9fafb !important;
            padding: 0.625rem 0.875rem !important;
            font-size: 0.95rem !important;
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s !important;
        }
        input[type="date"] {
            min-height: 2.75rem;
            line-height: 1.25;
            -webkit-appearance: none;
            appearance: none;
            overflow: hidden;
        }
        input[type="date"]::-webkit-date-and-time-value {
            min-height: 1.25rem;
            text-align: left;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            flex-shrink: 0;
            margin-left: 0.25rem;
        }
        input:not([type]):focus,
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        input[type="password"]:focus,
        input[type="search"]:focus,
        select:focus,
        textarea:focus {
            border-color: #00491E !important;
            background-color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(0, 73, 30, 0.15) !important;
        }
        input::placeholder,
        textarea::placeholder {
            color: #9ca3af !important;
        }
        .guest-field-invalid {
            border-color: #dc2626 !important;
            background-color: #fff7f7 !important;
        }
        .guest-validation-message {
            display: none;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            line-height: 1.25;
            color: #dc2626;
        }
        .guest-validation-message.is-visible {
            display: block;
        }
        .guest-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 2.75rem !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%23111827' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.9rem center;
            background-size: 1rem 1rem;
        }
        .guest-select::-ms-expand {
            display: none;
        }
        @keyframes tour-ping {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.5); }
        }
        .tour-pill-dot {
            animation: tour-ping 1.8s ease-in-out infinite;
        }
        .guest-desktop-nav {
            gap: 1rem;
        }
        .guest-desktop-nav > a {
            white-space: nowrap;
            flex-shrink: 0;
        }
        @media (min-width: 768px) and (max-width: 1180px) {
            .guest-desktop-nav {
                gap: 0.7rem;
            }
            .guest-desktop-nav > a {
                font-size: 0.92rem;
            }
            .guest-desktop-nav .guest-nav-pill,
            .guest-desktop-nav .guest-nav-cta {
                padding-left: 0.85rem;
                padding-right: 0.85rem;
            }
        }
        /* Accessibility: High Contrast */
        @if($highContrast)
        body {
            background: #000 !important;
            color: #fff !important;
        }
        body a,
        body .text-\[\#00491E\],
        body .text-gray-300,
        body .text-gray-400,
        body .text-gray-600,
        body .text-gray-700 {
            color: #FFD700 !important;
        }
        @endif
        /* Accessibility: Large Text */
        @if($largeText)
        body { font-size: 1.25em !important; }
        @endif
    </style>
    @stack('styles')
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
    {{-- Announcement Bar --}}
    @if($maintenanceMode && $maintenanceMessage)
        <div class="w-full bg-red-700 py-2 px-4 text-center font-bold text-base shadow-md" style="color:#FFC600; text-shadow: 0 1px 2px rgba(0,0,0,0.6);">
            <span class="inline-block align-middle"><i class="fas fa-tools mr-2"></i>{{ $maintenanceMessage }}</span>
        </div>
    @endif
    @if($showAnnouncement && $announcementText)
        <div class="w-full py-2 px-4 text-center font-bold text-base shadow-md" style="background-color: var(--cmu-yellow); color: #1a1a1a; text-shadow: 0 1px 1px rgba(255,255,255,0.4);">
            {{ $announcementText }}
        </div>
    @endif
    {{-- Navigation --}}
    <nav class="bg-[#00491E] shadow-lg">
        <div class="mx-auto max-w-[1700px] px-3 sm:px-5 lg:px-6">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="{{ route('guest.home', [], false) }}" class="flex items-center gap-3 group">
                        <div class="flex-shrink-0 bg-white rounded-lg p-1 shadow ring-2 ring-[#FFC600]/60 group-hover:ring-[#FFC600] transition">
                            <img src="{{ $logoSrc }}" alt="{{ $siteTitle }}" class="h-12 w-12 object-cover rounded" />
                        </div>
                        <div class="hidden sm:flex flex-col leading-tight">
                            <span class="guest-brand text-[#FFC600] font-extrabold text-xl tracking-wide group-hover:text-yellow-300 transition drop-shadow">{{ $brandName }}</span>
                            <span class="guest-brand text-white font-semibold text-sm tracking-wide group-hover:text-yellow-100 transition drop-shadow">{{ $institutionName }}</span>
                        </div>
                    </a>
                </div>
                <div class="hidden md:flex items-center guest-desktop-nav">
                    <a href="{{ route('guest.home', [], false) }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.home') ? 'text-[#FFC600]' : '' }}">{{ $guestSite['guest_nav_home_label'] }}</a>
                    <a href="{{ route('guest.about', [], false) }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.about') ? 'text-[#FFC600]' : '' }}">{{ $guestSite['guest_nav_about_label'] }}</a>
                    <a href="{{ route('guest.rooms', [], false) }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.rooms') ? 'text-[#FFC600]' : '' }}">{{ $guestSite['guest_nav_rooms_label'] }}</a>
                    <a href="{{ route('guest.track', [], false) }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.track') ? 'text-[#FFC600]' : '' }}">{{ $guestSite['guest_nav_track_label'] }}</a>
                    <a href="{{ route('guest.support', [], false) }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.support', 'guest.account.support.*') ? 'text-[#FFC600]' : '' }}">Support</a>
                    <a href="{{ route('guest.virtual-tours', [], false) }}" class="guest-nav-pill flex items-center gap-2 bg-[#FFC600] text-[#00491E] font-bold px-4 py-1.5 rounded-full shadow-[0_0_12px_rgba(255,198,0,0.45)] hover:shadow-[0_0_20px_rgba(255,198,0,0.7)] hover:bg-yellow-400 transition-all duration-200 {{ request()->routeIs('guest.virtual-tours') ? 'ring-2 ring-white' : '' }}">
                        <span class="relative flex items-center justify-center w-2 h-2">
                            <span class="tour-pill-dot absolute inline-flex w-full h-full rounded-full bg-red-600 opacity-60"></span>
                            <span class="relative inline-flex w-2 h-2 rounded-full bg-red-600"></span>
                        </span>
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        {{ $guestSite['guest_nav_tour_label'] }}
                    </a>
                    <a href="{{ route('guest.reserve', [], false) }}" class="guest-nav-cta bg-[#FFC600] text-[#00491E] px-4 py-2 rounded-lg font-bold transition-all duration-200 hover:bg-white hover:text-[#00491E] hover:scale-105 active:scale-95 {{ request()->routeIs('guest.reserve') ? 'ring-2 ring-white' : '' }}">{{ $guestSite['guest_nav_reserve_label'] }}</a>
                    @if(auth('guest')->check())
                        <a href="{{ route('guest.account.dashboard', [], false) }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.account.*') ? 'text-[#FFC600]' : '' }}">My Account</a>
                        <form method="POST" action="{{ route('guest.account.logout', [], false) }}">
                            @csrf
                            <button class="text-white hover:text-[#FFC600] transition font-medium">Logout</button>
                        </form>
                    @else
                        <a href="{{ route('guest.account.login', [], false) }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.account.login') ? 'text-[#FFC600]' : '' }}">Login</a>
                    @endif
                </div>
                {{-- Mobile menu button --}}
                <div class="md:hidden flex items-center">
                    <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="text-white hover:text-[#FFC600]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        {{-- Mobile menu --}}
        <div id="mobile-menu" class="hidden md:hidden bg-[#02681E] border-t border-[#00491E]">
            <div class="px-4 py-3 space-y-2">
                <a href="{{ route('guest.home', [], false) }}" class="block text-white hover:text-[#FFC600] py-2">{{ $guestSite['guest_nav_home_label'] }}</a>
                <a href="{{ route('guest.about', [], false) }}" class="block text-white hover:text-[#FFC600] py-2">{{ $guestSite['guest_nav_about_label'] }}</a>
                <a href="{{ route('guest.rooms', [], false) }}" class="block text-white hover:text-[#FFC600] py-2">{{ $guestSite['guest_nav_rooms_label'] }}</a>
                <a href="{{ route('guest.track', [], false) }}" class="block text-white hover:text-[#FFC600] py-2">{{ $guestSite['guest_nav_track_label'] }}</a>
                <a href="{{ route('guest.support', [], false) }}" class="block text-white hover:text-[#FFC600] py-2 {{ request()->routeIs('guest.account.support.*') ? 'text-[#FFC600]' : '' }}">Support</a>
                <a href="{{ route('guest.virtual-tours', [], false) }}" class="flex items-center gap-2 text-[#FFC600] hover:text-yellow-400 py-2 font-medium">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    {{ $guestSite['guest_nav_tour_label'] }}
                </a>
                <a href="{{ route('guest.reserve', [], false) }}" class="block text-[#FFC600] font-bold py-2">{{ $guestSite['guest_nav_reserve_label'] }}</a>
                @if(auth('guest')->check())
                    <a href="{{ route('guest.account.dashboard', [], false) }}" class="block text-white hover:text-[#FFC600] py-2">My Account</a>
                    <form method="POST" action="{{ route('guest.account.logout', [], false) }}">
                        @csrf
                        <button class="block w-full text-left text-white hover:text-[#FFC600] py-2">Logout</button>
                    </form>
                @else
                    <a href="{{ route('guest.account.login', [], false) }}" class="block text-white hover:text-[#FFC600] py-2">Login</a>
                @endif
            </div>
        </div>
    </nav>

    {{-- Flash Messages --}}
    @unless(trim($__env->yieldContent('suppressGlobalGuestFlashes')) === 'true')
        @include('guest.partials.flash-messages')
    @endunless

    {{-- Main Content --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-[#00491E] text-white mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-[var(--cmu-yellow)] font-bold text-lg mb-3">{{ $institutionName }}<br>{{ $brandName }}</h3>
                    @if($guestSite['guest_footer_address'])
                        <p class="text-gray-300 text-sm">{!! nl2br(e($guestSite['guest_footer_address'])) !!}</p>
                    @endif
                    @if($guestSite['guest_footer_phone'] || $guestSite['guest_footer_email'])
                        <p class="text-gray-300 text-sm mt-2">
                            @if($guestSite['guest_footer_phone'])
                                <span class="font-semibold">Phone:</span> {{ $guestSite['guest_footer_phone'] }}<br>
                            @endif
                            @if($guestSite['guest_footer_email'])
                                <span class="font-semibold">Email:</span> {{ $guestSite['guest_footer_email'] }}
                            @endif
                        </p>
                    @endif
                </div>
                <div>
                    <h3 class="text-[#FFC600] font-bold text-lg mb-3">Quick Links</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('guest.home', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">{{ $guestSite['guest_nav_home_label'] }}</a></li>
                        <li><a href="{{ route('guest.about', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">{{ $guestSite['guest_footer_about_label'] }}</a></li>
                        <li><a href="{{ route('guest.rooms', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">{{ $guestSite['guest_footer_rooms_label'] }}</a></li>
                        <li><a href="{{ route('guest.track', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">{{ $guestSite['guest_footer_track_label'] }}</a></li>
                        <li><a href="{{ route('guest.support', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">Support</a></li>
                        <li><a href="{{ route('guest.virtual-tours', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">{{ $guestSite['guest_footer_tour_label'] }}</a></li>
                        <li><a href="{{ route('guest.reserve', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">{{ $guestSite['guest_footer_reserve_label'] }}</a></li>
                        <li><a href="{{ auth('guest')->check() ? route('guest.account.dashboard', [], false) : route('guest.account.login', [], false) }}" class="text-gray-300 hover:text-[#FFC600] transition">Guest Account</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-[#02681E] mt-8 pt-6 text-center text-gray-400 text-sm">
                &copy; {{ date('Y') }} {{ $guestSite['guest_footer_copyright_name'] }}. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        window.GuestDatePairs = window.GuestDatePairs || (() => {
            const pairs = [
                ['check_in', 'check_out'],
                ['check_in_filter', 'check_out_filter'],
                ['check_in_date', 'check_out_date'],
            ];

            const toDateString = (date) => {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            };

            const addDays = (dateString, days) => {
                if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateString || ''))) return '';
                const [year, month, day] = dateString.split('-').map(Number);
                const date = new Date(year, month - 1, day);
                date.setDate(date.getDate() + days);
                return toDateString(date);
            };

            const findInput = (key) => document.getElementById(key) || document.querySelector(`[name="${key}"]`);

            const sync = (checkIn, checkOut) => {
                if (!checkIn || !checkOut || !checkIn.value) return;

                const minCheckOut = addDays(checkIn.value, 1);
                if (!minCheckOut) return;

                checkOut.min = minCheckOut;
                if (!checkOut.value || checkOut.value <= checkIn.value) {
                    checkOut.value = minCheckOut;
                }
            };

            const syncAll = () => {
                pairs.forEach(([checkInKey, checkOutKey]) => sync(findInput(checkInKey), findInput(checkOutKey)));
            };

            const handleDateInput = (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement) || target.type !== 'date') return;

                pairs.forEach(([checkInKey, checkOutKey]) => {
                    const checkIn = findInput(checkInKey);
                    const checkOut = findInput(checkOutKey);

                    if (target === checkIn || target === checkOut) {
                        sync(checkIn, checkOut);
                    }
                });
            };

            document.addEventListener('DOMContentLoaded', syncAll);
            document.addEventListener('input', handleDateInput);
            document.addEventListener('change', handleDateInput);

            return { addDays, sync, syncAll };
        })();
    </script>
    <script>
        window.GuestRealtimeValidation = window.GuestRealtimeValidation || (() => {
            const selector = 'input:not([type="hidden"]):not([type="radio"]), select, textarea';

            const getLabelText = (field) => {
                const label = field.id ? document.querySelector(`label[for="${CSS.escape(field.id)}"]`) : null;
                return (label?.textContent || field.name || 'This field').replace(/\s*\*\s*$/, '').trim();
            };

            const messageFor = (field) => {
                const label = getLabelText(field);
                const validity = field.validity;

                if (validity.valueMissing) return `${label} is required.`;
                if (validity.typeMismatch && field.type === 'email') return 'Enter a valid email address.';
                if (validity.rangeUnderflow) return field.dataset.validationMinMessage || `${label} must be at least ${field.min}.`;
                if (validity.rangeOverflow) return field.dataset.validationMaxMessage || `${label} must be no more than ${field.max}.`;
                if (validity.tooLong) return `${label} must be ${field.maxLength} characters or fewer.`;
                if (validity.badInput) return `Enter a valid ${field.type === 'number' ? 'number' : 'value'}.`;
                if (validity.patternMismatch) return field.dataset.validationPatternMessage || `${label} has an invalid format.`;
                if (validity.customError) return field.validationMessage;

                return field.validationMessage || `${label} is invalid.`;
            };

            const ensureMessageEl = (field) => {
                if (!field.id) {
                    field.id = `guest-field-${Math.random().toString(36).slice(2)}`;
                }

                const messageId = `${field.id}-validation`;
                let message = document.getElementById(messageId);

                if (!message) {
                    message = document.createElement('p');
                    message.id = messageId;
                    message.className = 'guest-validation-message';
                    message.setAttribute('aria-live', 'polite');
                    field.insertAdjacentElement('afterend', message);
                }

                field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), messageId].filter(Boolean).join(' '));
                return message;
            };

            const syncConditionalFields = (form) => {
                const discountDeclared = form.querySelector('[name="discount_declared"]');
                const discountType = form.querySelector('[name="discount_declared_type"]');
                if (discountDeclared && discountType) {
                    discountType.required = discountDeclared.checked;
                }
            };

            const syncCustomValidity = (field, form) => {
                field.setCustomValidity('');

                if (field.dataset.integer === 'true' && field.value && !Number.isInteger(Number(field.value))) {
                    field.setCustomValidity(`${getLabelText(field)} must be a whole number.`);
                }

                if (['guest_first_name', 'guest_last_name'].includes(field.name) && field.required && field.value.trim() === '') {
                    field.setCustomValidity(`${getLabelText(field)} is required.`);
                }

                if (field.name === 'check_out_date') {
                    const checkIn = form.querySelector('[name="check_in_date"]');
                    if (checkIn?.value && field.value && field.value <= checkIn.value) {
                        field.setCustomValidity('Check-out date must be after check-in date.');
                    }
                }

                if (field.name === 'number_of_occupants' && field.dataset.dynamicMax !== undefined && field.value) {
                    const dynamicMax = Number(field.dataset.dynamicMax);
                    const occupants = Number(field.value);
                    if (Number.isFinite(dynamicMax) && (dynamicMax < 1 || occupants > dynamicMax)) {
                        field.setCustomValidity(
                            field.dataset.validationMaxMessage || `${getLabelText(field)} must be no more than ${dynamicMax}.`
                        );
                    }
                }

                if (field.name === 'password_confirmation') {
                    const password = form.querySelector('[name="password"]');
                    if (password?.value && field.value && field.value !== password.value) {
                        field.setCustomValidity('Password confirmation must match the password.');
                    }
                }
            };

            const validateField = (field, form, force = false) => {
                if (field.disabled || field.type === 'hidden') return true;

                syncConditionalFields(form);
                syncCustomValidity(field, form);

                const isValid = field.checkValidity();
                const shouldShow = force || field.dataset.touched === 'true' || field.value !== '';
                const message = ensureMessageEl(field);

                field.classList.toggle('guest-field-invalid', !isValid && shouldShow);
                field.setAttribute('aria-invalid', String(!isValid && shouldShow));
                message.textContent = !isValid && shouldShow ? messageFor(field) : '';
                message.classList.toggle('is-visible', !isValid && shouldShow);

                return isValid;
            };

            const validateForm = (form, force = false) => {
                syncConditionalFields(form);
                return Array.from(form.querySelectorAll(selector))
                    .reduce((isFormValid, field) => validateField(field, form, force) && isFormValid, true);
            };

            const initForm = (form) => {
                form.querySelectorAll(selector).forEach((field) => {
                    ensureMessageEl(field);

                    field.addEventListener('blur', () => {
                        field.dataset.touched = 'true';
                        validateField(field, form);
                    });

                    field.addEventListener('input', () => {
                        field.dataset.touched = 'true';
                        validateField(field, form);

                        if (field.name === 'check_in_date') {
                            const checkOut = form.querySelector('[name="check_out_date"]');
                            if (checkOut) validateField(checkOut, form, true);
                        }

                        if (field.name === 'password') {
                            const confirmation = form.querySelector('[name="password_confirmation"]');
                            if (confirmation) validateField(confirmation, form, confirmation.value !== '');
                        }
                    });

                    field.addEventListener('change', () => {
                        field.dataset.touched = 'true';
                        validateField(field, form);

                        if (field.name === 'check_in_date') {
                            const checkOut = form.querySelector('[name="check_out_date"]');
                            if (checkOut) validateField(checkOut, form, true);
                        }

                        if (field.name === 'discount_declared') {
                            const discountType = form.querySelector('[name="discount_declared_type"]');
                            if (discountType) validateField(discountType, form, true);
                        }
                    });
                });

                form.addEventListener('submit', (event) => {
                    if (!validateForm(form, true)) {
                        event.preventDefault();
                        const firstInvalid = form.querySelector('.guest-field-invalid');
                        firstInvalid?.focus({ preventScroll: true });
                        firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });

                validateForm(form);
            };

            const init = () => {
                document.querySelectorAll('form[data-guest-validate]').forEach(initForm);
            };

            document.addEventListener('DOMContentLoaded', init);

            return { init, validateForm, validateField };
        })();
    </script>
    @stack('scripts')
</body>
</html>
