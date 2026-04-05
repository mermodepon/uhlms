@php
    $isLogin = request()->routeIs('filament.admin.auth.login');
@endphp

<div class="flex items-center {{ $isLogin ? 'gap-3' : 'gap-2' }}">
    <img src="{{ asset('images/uh_logo.jpg') }}" alt="UH Lodging Management System" style="height:{{ $isLogin ? '3rem' : '1.5rem' }}; width:auto;" />
    <span class="filament-brand-text font-semibold leading-tight text-gray-800 dark:text-white">
        <span style="display:block;font-size:{{ $isLogin ? '0.75rem' : '0.5rem' }};line-height:1.1;">Central Mindanao University</span>
        <span style="display:block;font-size:{{ $isLogin ? '1.25rem' : '0.875rem' }};line-height:1.2;">UH Lodging Management System</span>
    </span>
</div>
