<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        use App\Models\Setting;
        $siteTitle = Setting::get('site_title', 'CMU University Homestay');
            $maintenanceMode = Setting::get('maintenance_mode');
            $maintenanceMessage = Setting::get('maintenance_message');
            $highContrast = Setting::get('accessibility_high_contrast');
            $largeText = Setting::get('accessibility_large_text');
        $siteTagline = Setting::get('site_tagline', 'Lodging Management System');
        $siteLogo = Setting::get('site_logo');
        $logoSrc = $siteLogo ? asset('storage/' . $siteLogo) : asset('images/uh_logo.jpg');
        $themeColor = Setting::get('theme_color', '#FFC600');
        $themeFont = Setting::get('theme_font', 'sans');
    @endphp
    <title>@yield('title', $siteTitle) - {{ $siteTagline }}</title>
    <link rel="icon" type="image/png" href="{{ $logoSrc }}">
    <link rel="apple-touch-icon" href="{{ $logoSrc }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --cmu-yellow: {{ $themeColor }};
            --cmu-green: #00491E;
            --cmu-green-alt1: #02681E;
            --cmu-green-alt2: #919F02;
        }
        body {
            font-family: {{ $themeFont == 'serif' ? 'Georgia, Times, serif' : ($themeFont == 'mono' ? 'Menlo, Monaco, monospace' : 'Inter, Arial, sans-serif') }};
        }
        /* Improve form input visibility */
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        input[type="date"],
        input[type="password"],
        input[type="search"],
        select,
        textarea {
            border: 2px solid #d1d5db !important;
            background-color: #f9fafb !important;
            padding: 0.625rem 0.875rem !important;
            font-size: 0.95rem !important;
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s !important;
        }
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
    @php
        $showAnnouncement = Setting::get('show_announcement');
        $announcementText = Setting::get('announcement_text');
    @endphp
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('guest.home') }}" class="flex items-center gap-3 group">
                        <div class="flex-shrink-0 bg-white rounded-lg p-1 shadow ring-2 ring-[#FFC600]/60 group-hover:ring-[#FFC600] transition">
                            <img src="{{ $logoSrc }}" alt="{{ $siteTitle }}" class="h-9 w-9 object-cover rounded" />
                        </div>
                        <div class="hidden sm:flex flex-col leading-tight">
                            <span class="text-[#FFC600] font-extrabold text-lg tracking-wide group-hover:text-yellow-300 transition drop-shadow">{{ $siteTitle }}</span>
                        </div>
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="{{ route('guest.home') }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.home') ? 'text-[#FFC600]' : '' }}">Home</a>
                    <a href="{{ route('guest.rooms') }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.rooms') ? 'text-[#FFC600]' : '' }}">Rooms</a>
                    <a href="{{ route('guest.virtual-tours') }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.virtual-tours') ? 'text-[#FFC600]' : '' }}">Virtual Tours</a>
                    <a href="{{ route('guest.reserve') }}" class="bg-[#FFC600] text-[#00491E] px-4 py-2 rounded-lg font-bold transition-all duration-200 hover:bg-white hover:text-[#00491E] hover:scale-105 active:scale-95 {{ request()->routeIs('guest.reserve') ? 'ring-2 ring-white' : '' }}">Reserve Now</a>
                    <a href="{{ route('guest.track') }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.track') ? 'text-[#FFC600]' : '' }}">Track Status</a>
                    <a href="{{ route('guest.messages') }}" class="text-white hover:text-[#FFC600] transition font-medium {{ request()->routeIs('guest.messages') ? 'text-[#FFC600]' : '' }}">Contact Us</a>
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
                <a href="{{ route('guest.home') }}" class="block text-white hover:text-[#FFC600] py-2">Home</a>
                <a href="{{ route('guest.rooms') }}" class="block text-white hover:text-[#FFC600] py-2">Rooms</a>
                <a href="{{ route('guest.virtual-tours') }}" class="block text-white hover:text-[#FFC600] py-2">Virtual Tours</a>
                <a href="{{ route('guest.reserve') }}" class="block text-[#FFC600] font-bold py-2">Reserve Now</a>
                <a href="{{ route('guest.track') }}" class="block text-white hover:text-[#FFC600] py-2">Track Status</a>
                <a href="{{ route('guest.messages') }}" class="block text-white hover:text-[#FFC600] py-2">Contact Us</a>
            </div>
        </div>
    </nav>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    {{-- Main Content --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-[#00491E] text-white mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-[var(--cmu-yellow)] font-bold text-lg mb-3">{{ $siteTitle }}</h3>
                    <p class="text-gray-300 text-sm">
                        {!! nl2br(e(Setting::get('contact_address', 'Central Mindanao University
Musuan, Maramag, Bukidnon
Philippines'))) !!}
                    </p>
                    <p class="text-gray-300 text-sm mt-2">
                        <span class="font-semibold">Phone:</span> {{ Setting::get('contact_phone', '') }}<br>
                        <span class="font-semibold">Email:</span> {{ Setting::get('contact_email', '') }}
                    </p>
                    @if(Setting::get('contact_map_embed'))
                        <div class="mt-2">
                            <iframe src="{{ Setting::get('contact_map_embed') }}" width="100%" height="80" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        </div>
                    @endif
                    <div class="flex gap-2 mt-3">
                        @if(Setting::get('social_facebook'))
                            <a href="{{ Setting::get('social_facebook') }}" target="_blank" class="text-[#FFC600] hover:text-white"><i class="fab fa-facebook"></i> Facebook</a>
                        @endif
                        @if(Setting::get('social_instagram'))
                            <a href="{{ Setting::get('social_instagram') }}" target="_blank" class="text-[#FFC600] hover:text-white"><i class="fab fa-instagram"></i> Instagram</a>
                        @endif
                        @if(Setting::get('social_twitter'))
                            <a href="{{ Setting::get('social_twitter') }}" target="_blank" class="text-[#FFC600] hover:text-white"><i class="fab fa-twitter"></i> Twitter</a>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-[#FFC600] font-bold text-lg mb-3">Quick Links</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('guest.rooms') }}" class="text-gray-300 hover:text-[#FFC600] transition">Room Catalog</a></li>
                        <li><a href="{{ route('guest.virtual-tours') }}" class="text-gray-300 hover:text-[#FFC600] transition">Virtual Tours</a></li>
                        <li><a href="{{ route('guest.reserve') }}" class="text-gray-300 hover:text-[#FFC600] transition">Make a Reservation</a></li>
                        <li><a href="{{ route('guest.track') }}" class="text-gray-300 hover:text-[#FFC600] transition">Track Reservation</a></li>
                        <li><a href="{{ route('guest.messages') }}" class="text-gray-300 hover:text-[#FFC600] transition">Contact Us</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-[#FFC600] font-bold text-lg mb-3">Staff Access</h3>
                    <a href="{{ url('/admin') }}" class="inline-block bg-[#02681E] text-white px-4 py-2 rounded hover:bg-[#00491E] transition text-sm border border-[#FFC600]/30">Staff Login</a>
                </div>
            </div>
            <div class="border-t border-[#02681E] mt-8 pt-6 text-center text-gray-400 text-sm">
                &copy; {{ date('Y') }} CMU University Homestay Lodging Management System. All rights reserved.
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
