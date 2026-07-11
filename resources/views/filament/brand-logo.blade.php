@php
    $isLogin = request()->routeIs('filament.admin.auth.login');
@endphp

<div class="uh-brand-logo flex items-center gap-3">
    <img src="{{ asset('images/uh_logo.jpg') }}" alt="UH Lodging Management System" style="height:{{ $isLogin ? '3.5rem' : '2rem' }}; width:auto;" />
    <span class="filament-brand-text font-semibold leading-tight">
        <span class="uh-brand-title" style="display:block;font-size:{{ $isLogin ? '1.5rem' : '1.1rem' }};line-height:1.2;color:#FFC600;">UH Lodging Management System</span>
        <span class="uh-brand-subtitle" style="display:block;font-size:{{ $isLogin ? '0.875rem' : '0.65rem' }};line-height:1.1;color:rgba(255,255,255,0.8);">Central Mindanao University</span>
    </span>
</div>
