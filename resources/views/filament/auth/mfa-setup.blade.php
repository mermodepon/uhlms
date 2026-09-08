<x-admin-security-layout title="Multi-Factor Authentication" description="Protect your staff account with a time-based authenticator code.">
    @if(session('mfa_recovery_codes'))
        <div class="mb-6 rounded-lg bg-amber-50 p-4 text-amber-950 ring-1 ring-amber-200">
            <p class="font-semibold">Save these recovery codes now. They will not be shown again.</p>
            <ul class="mt-3 grid gap-2 font-mono text-sm sm:grid-cols-2">
                @foreach(session('mfa_recovery_codes') as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($enabled)
        <div class="rounded-lg bg-green-50 p-4 text-green-900 ring-1 ring-green-200">
            MFA is enabled for {{ $user->email }}.
        </div>

        <form method="POST" action="{{ route('admin.mfa.recovery-codes') }}" class="mt-6 space-y-4">
            @csrf
            <label for="password" class="block text-sm font-medium">Current password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required class="block w-full rounded-lg border-gray-300 shadow-sm">
            <button type="submit" class="w-full rounded-lg bg-gray-800 px-4 py-2.5 font-semibold text-white hover:bg-gray-700">Generate new recovery codes</button>
        </form>

        @unless(\App\Support\AdminMfa::isMandatory($user))
            <form method="POST" action="{{ route('admin.mfa.disable') }}" class="mt-4 space-y-4">
                @csrf
                @method('DELETE')
                <input type="password" name="password" autocomplete="current-password" placeholder="Current password" required class="block w-full rounded-lg border-gray-300 shadow-sm">
                <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-2.5 font-semibold text-white hover:bg-red-500">Disable MFA</button>
            </form>
        @endunless

        <a href="/admin" class="mt-6 block text-center font-semibold text-primary-700 hover:underline">Continue to the admin panel</a>
    @elseif($pendingConfirmation)
        <div class="mb-5 flex justify-center rounded-xl bg-white p-4 ring-1 ring-gray-200">
            {!! $qrCode !!}
        </div>
        <p class="mb-5 text-sm text-gray-600">Scan the QR code, then enter the six-digit code shown in your authenticator app.</p>
        <form method="POST" action="{{ route('admin.mfa.confirm') }}" class="space-y-4">
            @csrf
            <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
                   class="block w-full rounded-lg border-gray-300 text-center font-mono text-xl tracking-[0.4em] shadow-sm">
            <button type="submit" class="w-full rounded-lg bg-primary-600 px-4 py-2.5 font-semibold text-white hover:bg-primary-500">Confirm and enable MFA</button>
        </form>
    @else
        <p class="mb-5 text-sm text-gray-600">You will need an authenticator application. Confirm your password to create a unique setup QR code.</p>
        <form method="POST" action="{{ route('admin.mfa.enable') }}" class="space-y-4">
            @csrf
            <label for="password" class="block text-sm font-medium">Current password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required autofocus class="block w-full rounded-lg border-gray-300 shadow-sm">
            <button type="submit" class="w-full rounded-lg bg-primary-600 px-4 py-2.5 font-semibold text-white hover:bg-primary-500">Begin MFA setup</button>
        </form>
        <a href="/admin/profile" class="security-profile-link">Open profile</a>
    @endif

    <form method="POST" action="/admin/logout" class="security-signout-form">
        @csrf
        <button type="submit" class="security-signout-button">Sign out</button>
    </form>
</x-admin-security-layout>
