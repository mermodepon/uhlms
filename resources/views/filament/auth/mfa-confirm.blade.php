<x-admin-security-layout title="Security Confirmation" description="This sensitive action requires a fresh multi-factor authentication check.">
    <form method="POST" action="{{ route('admin.mfa.recent.verify') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="mb-1 block text-sm font-medium">Authenticator code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                   class="block w-full rounded-lg border-gray-300 text-center font-mono text-xl tracking-[0.4em] shadow-sm">
        </div>
        <button type="submit" class="w-full rounded-lg bg-primary-600 px-4 py-2.5 font-semibold text-white hover:bg-primary-500">Confirm</button>
    </form>

    <div class="my-6 border-t border-gray-200 dark:border-gray-700"></div>

    <form method="POST" action="{{ route('admin.mfa.recent.verify') }}" class="space-y-4">
        @csrf
        <div>
            <label for="recovery_code" class="mb-1 block text-sm font-medium">Recovery code</label>
            <input id="recovery_code" name="recovery_code" autocomplete="one-time-code"
                   class="block w-full rounded-lg border-gray-300 font-mono shadow-sm">
        </div>
        <button type="submit" class="w-full rounded-lg bg-gray-800 px-4 py-2.5 font-semibold text-white hover:bg-gray-700">Use recovery code</button>
    </form>
</x-admin-security-layout>
