@extends('layouts.guest')

@section('title', 'My Support Threads')

@section('suppressGlobalGuestFlashes', 'true')
@section('content')
    <section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[#00491E]">Support</h1>
                <p class="text-gray-600">Ask questions, report issues, or get help from our staff.</p>
            </div>
            @if($account->hasVerifiedEmail())
                <button onclick="document.getElementById('new-thread-form').classList.toggle('hidden')"
                        class="rounded-lg bg-[#00491E] px-5 py-3 font-bold text-white hover:bg-[#02681E]">
                    New Inquiry
                </button>
            @endif
        </div>

        @include('guest.partials.flash-messages', ['wrap' => false, 'containerClass' => 'mb-6 space-y-3'])

        @error('support')
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror

        @unless($account->hasVerifiedEmail())
            <div class="mb-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                <form method="POST" action="{{ route('guest.account.verification.send', [], false) }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    @csrf
                    <span>Verify your email before sending support messages. You can still read your existing threads.</span>
                    <button class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Resend Verification</button>
                </form>
            </div>
        @endunless

        {{-- New inquiry form (hidden by default) --}}
        @if($account->hasVerifiedEmail())
        <div id="new-thread-form" class="mb-8 hidden rounded-xl border border-[#00491E]/30 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-lg font-bold text-[#00491E]">New Support Inquiry</h2>
            <form method="POST" action="{{ route('guest.account.support.submit', [], false) }}" novalidate>
                @csrf

                @if($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        Please review the highlighted fields and try again.
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select id="category" name="category" required class="guest-select rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                            <option value="">Select a category...</option>
                            @foreach(\App\Models\SupportInquiry::categoryOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                        <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required maxlength="255"
                               class="rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]">
                        @error('subject') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                    <textarea id="message" name="message" rows="4" required minlength="10" maxlength="5000"
                              class="rounded-lg border-gray-300 shadow-sm focus:border-[#00491E] focus:ring-[#00491E]"
                              placeholder="Describe your question or concern...">{{ old('message') }}</textarea>
                    @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('new-thread-form').classList.add('hidden')"
                            class="rounded-lg bg-gray-200 px-4 py-2 font-semibold text-gray-700">Cancel</button>
                    <button class="rounded-lg bg-[#00491E] px-5 py-2.5 font-bold text-white hover:bg-[#02681E]">Submit Inquiry</button>
                </div>
            </form>
        </div>
        @endif

        {{-- Thread list --}}
        <div class="space-y-3">
            @forelse($threads as $inquiry)
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
                @endphp
                <a href="{{ route('guest.account.support.show', $inquiry, false) }}"
                   class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-[#00491E] transition-colors">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-gray-900 truncate">{{ $inquiry->subject }}</span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusColors[$inquiry->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $statusLabels[$inquiry->status] ?? $inquiry->status }}
                                </span>
                            </div>
                            <div class="mt-1 text-sm text-gray-500 truncate">{{ $inquiry->message }}</div>
                        </div>
                        <div class="shrink-0 text-right text-sm text-gray-400 space-y-0.5">
                            <div>{{ $inquiry->created_at->format('M d, Y') }}</div>
                            @if($inquiry->replies_count > 0)
                                <div class="text-[#00491E] font-medium">{{ $inquiry->replies_count }} {{ Str::plural('reply', $inquiry->replies_count) }}</div>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                    <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    <p class="text-gray-500 font-medium">No support inquiries yet.</p>
                    <p class="mt-1 text-sm text-gray-400">Click <strong>New Inquiry</strong> above to get help from our staff.</p>
                </div>
            @endforelse
        </div>

        @if($threads->hasPages())
            <div class="mt-6">{{ $threads->links() }}</div>
        @endif
    </section>
@endsection
