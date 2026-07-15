@extends('layouts.guest')

@section('title', 'Support - ' . $inquiry->subject)
@section('suppressGlobalGuestFlashes', 'true')

@section('content')
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-6">
            <a href="{{ route('guest.account.support.index', [], false) }}"
               class="inline-flex items-center gap-1 text-sm font-medium text-[#00491E] hover:underline mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Support
            </a>

            @php
                $statusColors = [
                    'new' => 'bg-yellow-100 text-yellow-800',
                    'in_progress' => 'bg-blue-100 text-blue-800',
                    'resolved' => 'bg-green-100 text-green-800',
                    'spam' => 'bg-red-100 text-red-800',
                    'archived' => 'bg-gray-100 text-gray-600',
                ];
                $statusLabels = [
                    'new' => 'New',
                    'in_progress' => 'In Progress',
                    'resolved' => 'Resolved',
                    'spam' => 'Spam',
                    'archived' => 'Archived',
                ];
                $categoryLabels = \App\Models\SupportInquiry::categoryOptions();
            @endphp

            <div class="flex flex-wrap items-start gap-3 justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $inquiry->subject }}</h1>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                        <span>{{ $categoryLabels[$inquiry->category] ?? $inquiry->category }}</span>
                        <span>&bull;</span>
                        <span>Opened {{ $inquiry->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
                <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $statusColors[$inquiry->status] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $statusLabels[$inquiry->status] ?? $inquiry->status }}
                </span>
            </div>
        </div>

        @include('guest.partials.flash-messages', ['wrap' => false, 'containerClass' => 'mb-6 space-y-3'])
        @include('guest.account.support.thread', ['inquiry' => $inquiry])

        @if(!in_array($inquiry->status, ['spam', 'archived']) && $account->hasVerifiedEmail())
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">
                    {{ $inquiry->status === 'resolved' ? 'Send a follow-up (this will re-open the thread)' : 'Reply' }}
                </h2>
                <form method="POST" action="{{ route('guest.account.support.reply', $inquiry, false) }}" novalidate>
                    @csrf

                    @error('message')
                        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</div>
                    @enderror

                    <textarea name="message" rows="3" required minlength="2" maxlength="2000"
                              class="rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E] resize-none"
                              placeholder="Type your message..."></textarea>

                    <div class="mt-3 flex justify-end">
                        <button class="rounded-lg bg-[#00491E] px-5 py-2.5 font-bold text-white hover:bg-[#02681E]">Send Reply</button>
                    </div>
                </form>
            </div>
        @elseif(!in_array($inquiry->status, ['spam', 'archived']))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                <form method="POST" action="{{ route('guest.account.verification.send', [], false) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    @csrf
                    <span>Verify your email before replying. You can still read this thread.</span>
                    <button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Resend Verification</button>
                </form>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-200 p-5 text-center text-sm text-gray-400">
                This thread has been archived. <a href="{{ route('guest.account.support.index', [], false) }}" class="text-[#00491E] font-medium hover:underline">Open a new inquiry</a> if you need further help.
            </div>
        @endif
    </section>
@endsection

@push('scripts')
    <script @if(request()->attributes->get('csp_nonce')) nonce="{{ request()->attributes->get('csp_nonce') }}" @endif>
        (() => {
            const messagesUrl = @json(route('guest.account.support.messages', $inquiry, false));
            let refreshing = false;

            const refreshThread = async () => {
                const currentThread = document.getElementById('support-thread');
                if (document.hidden || refreshing || !currentThread) return;

                refreshing = true;

                try {
                    const response = await fetch(messagesUrl, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/json' },
                    });

                    if (!response.ok) return;

                    const payload = await response.json();
                    if (Number(payload.lastReplyId) === Number(currentThread.dataset.lastReplyId)) return;

                    const nearBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 180;
                    currentThread.outerHTML = payload.html;

                    if (nearBottom) {
                        requestAnimationFrame(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }));
                    }
                } catch (_) {
                    // A later poll will retry if the connection is briefly unavailable.
                } finally {
                    refreshing = false;
                }
            };

            window.setInterval(refreshThread, 5000);
        })();
    </script>
@endpush
