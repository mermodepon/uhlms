@extends('layouts.guest')

@section('title', 'Support Access')

@section('content')
    <section class="bg-gradient-to-r from-[#00491E] to-[#02681E] py-10 text-white sm:py-12">
        <div class="mx-auto max-w-4xl px-6 text-center sm:px-8 lg:px-10">
            <h1 class="text-3xl font-bold sm:text-4xl">Support is available to verified guest accounts</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-green-100">
                Create an account to send inquiries and keep every conversation with our staff in one secure place.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10">
        <div class="space-y-8 sm:space-y-10">
            <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm sm:p-10">
                <h2 class="text-2xl font-bold text-[#00491E]">Why create a guest account?</h2>
                <p class="mt-3 max-w-2xl leading-relaxed text-gray-600">After confirming your email, you can use Support and get more from your future stays.</p>

                <ul class="mt-8 space-y-4 text-gray-700">
                    <li class="flex gap-4 rounded-xl bg-[#f4faf6] p-4 sm:p-5">
                        <span class="mt-0.5 font-bold text-[#00491E]">&check;</span>
                        <span><strong>Secure support conversations:</strong> send inquiries and read staff replies from your private account.</span>
                    </li>
                    <li class="flex gap-4 rounded-xl bg-[#f4faf6] p-4 sm:p-5">
                        <span class="mt-0.5 font-bold text-[#00491E]">&check;</span>
                        <span><strong>Keep your history together:</strong> revisit support threads and reservation details when you need them.</span>
                    </li>
                    <li class="flex gap-4 rounded-xl bg-[#f4faf6] p-4 sm:p-5">
                        <span class="mt-0.5 font-bold text-[#00491E]">&check;</span>
                        <span><strong>Faster future stays:</strong> reuse your saved profile details instead of entering them again.</span>
                    </li>
                    <li class="flex gap-4 rounded-xl bg-[#f4faf6] p-4 sm:p-5">
                        <span class="mt-0.5 font-bold text-[#00491E]">&check;</span>
                        <span><strong>Verified access:</strong> email confirmation helps protect your account and reduces spam in our support inbox.</span>
                    </li>
                </ul>
            </div>

            <aside class="rounded-2xl border border-[#00491E]/20 bg-[#f4faf6] p-8 shadow-sm sm:p-10">
                <h2 class="text-xl font-bold text-[#00491E]">Ready to contact Support?</h2>
                <p class="mt-3 max-w-xl leading-relaxed text-gray-600">Create an account, verify your email, then sign in to open a support thread.</p>
                <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <a href="{{ route('guest.account.register', [], false) }}" class="rounded-lg bg-[#00491E] px-6 py-3 text-center font-bold text-white hover:bg-[#02681E]">Create a Guest Account</a>
                    <a href="{{ route('guest.account.login', [], false) }}" class="px-2 text-center font-semibold text-[#00491E] hover:underline">Already have an account? Log in</a>
                </div>
            </aside>
        </div>

        <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-8 text-amber-950 shadow-sm sm:mt-10 sm:p-10">
            <h2 class="text-lg font-bold">You do not need an account to request a stay.</h2>
            <p class="mt-3 max-w-2xl leading-relaxed">You can still submit a reservation request as a guest. Creating an account is optional for reservations, but required for Support messaging.</p>
            <a href="{{ route('guest.reserve', [], false) }}" class="mt-6 inline-block rounded-lg bg-amber-600 px-6 py-3 font-bold text-white hover:bg-amber-700">Request a Stay</a>
        </div>
    </section>
@endsection
