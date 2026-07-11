<x-filament-panels::page>
    <style>
        .support-inbox {
            height: calc(100vh - 11rem);
            min-height: 32rem;
            overflow: hidden;
            display: flex;
            border: 1px solid #dfe5e1;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }
        .support-inbox__list { width: 22rem; flex-shrink: 0; border-right: 1px solid #e5e7eb; background: #ffffff; }
        .support-inbox__list-heading { padding: 1.25rem 1.35rem; border-bottom: 1px solid #e5e7eb; background: #f8faf9; }
        .support-inbox__item { padding: 1rem 1.25rem; }
        .support-inbox__header { padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; background: #f8faf9; }
        .support-inbox__messages { padding: 1.5rem; gap: 1.25rem; background: #fcfdfc; }
        .support-message { padding: 0.9rem 1rem; border-radius: 1rem; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }
        .support-message--guest { background: #f1f5f3; color: #1f2937; border: 1px solid #e2e8e5; }
        .support-message--staff { background: #00491E !important; color: #ffffff !important; border: 1px solid #00491E; }
        .support-message--staff p { color: #ffffff !important; }
        .support-message__meta { margin-top: 0.45rem; color: #94a3b8; }
        .support-inbox__composer { padding: 1rem 1.25rem 1.15rem; border-top: 1px solid #e5e7eb; background: #ffffff; }
        .support-inbox__composer textarea { padding: 0.75rem 0.9rem; background: #ffffff; }
        @media (max-width: 768px) {
            .support-inbox { height: auto; min-height: 38rem; flex-direction: column; }
            .support-inbox__list { width: 100%; max-height: 15rem; border-right: 0; border-bottom: 1px solid #e5e7eb; }
            .support-inbox__messages { min-height: 20rem; padding: 1rem; }
        }
    </style>

    @php
        $inquiries = $this->getInquiries();
        $selected  = $this->getSelectedInquiry();
        $categories = \App\Models\SupportInquiry::categoryOptions();
    @endphp

    <div wire:poll.5s class="support-inbox flex overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
         style="height: calc(100vh - 11rem); min-height: 480px;">

        {{-- ── Thread list (left panel) ──────────────────────────── --}}
        <div class="support-inbox__list flex w-72 shrink-0 flex-col border-r border-gray-200">
            <div class="support-inbox__list-heading border-b border-gray-200 bg-gray-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Conversations</p>
                <p class="mt-0.5 text-sm text-gray-500">{{ $inquiries->count() }} {{ Str::plural('inquiry', $inquiries->count()) }}</p>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
                @forelse($inquiries as $inquiry)
                    @php
                        $isSelected  = $this->selectedId === $inquiry->id;
                        $hasNoReply  = $inquiry->replies_count === 0;
                        $initial     = strtoupper(mb_substr($inquiry->name, 0, 1));
                        $preview     = $inquiry->latestReply
                            ? Str::limit($inquiry->latestReply->message, 55)
                            : Str::limit($inquiry->message, 55);
                    @endphp
                    <button wire:click="selectThread({{ $inquiry->id }})"
                            class="support-inbox__item flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50
                                   {{ $isSelected ? 'bg-green-50 border-l-[3px] border-l-[#00491E]' : 'border-l-[3px] border-l-transparent' }}">
                        {{-- Avatar --}}
                        <div class="relative mt-0.5 shrink-0">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#00491E] text-sm font-bold text-white">
                                {{ $initial }}
                            </div>
                            @if($hasNoReply)
                                <span class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-yellow-400 ring-2 ring-white"></span>
                            @endif
                        </div>

                        {{-- Meta --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-1">
                                <span class="truncate text-sm font-semibold {{ $hasNoReply ? 'text-gray-900' : 'text-gray-600' }}">
                                    {{ $inquiry->name }}
                                </span>
                                <span class="shrink-0 text-[10px] text-gray-400">
                                    {{ $inquiry->created_at->diffForHumans(null, true, true) }}
                                </span>
                            </div>
                            <div class="truncate text-xs {{ $hasNoReply ? 'font-semibold text-gray-700' : 'text-gray-500' }}">
                                {{ $inquiry->subject }}
                            </div>
                            <div class="truncate text-xs text-gray-400">{{ $preview }}</div>
                        </div>
                    </button>
                @empty
                    <div class="flex flex-1 items-center justify-center py-12 text-sm text-gray-400">
                        No inquiries yet.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ── Conversation panel (right) ────────────────────────── --}}
        <div class="flex min-w-0 flex-1 flex-col">

            @if($selected)
                {{-- Conversation header --}}
                <div class="support-inbox__header flex items-center justify-between border-b border-gray-200 bg-gray-50 px-5 py-3">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $selected->name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $selected->email }}
                            &nbsp;&bull;&nbsp;
                            {{ $categories[$selected->category] ?? $selected->category }}
                            @if($selected->guestAccount)
                                &nbsp;&bull;&nbsp;
                                <span class="text-green-700 font-medium">Guest Account</span>
                            @else
                                &nbsp;&bull;&nbsp;
                                <span class="text-gray-400">Unregistered</span>
                            @endif
                        </p>
                    </div>
                    <p class="text-xs text-gray-400">{{ $selected->created_at->format('M d, Y') }}</p>
                </div>

                {{-- Messages --}}
                <div class="support-inbox__messages flex-1 overflow-y-auto space-y-3 p-5"
                     x-data
                     x-init="$el.scrollTop = $el.scrollHeight"
                     x-on:livewire:updated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">

                    {{-- Original inquiry message (guest side) --}}
                    <div class="flex justify-end">
                        <div class="max-w-[72%]">
                            <div class="support-message support-message--guest rounded-2xl rounded-tr-sm bg-gray-100 px-4 py-2.5">
                                <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $selected->message }}</p>
                            </div>
                            <p class="support-message__meta mt-1 text-right text-[11px] text-gray-400">
                                {{ $selected->name }} &bull; {{ $selected->created_at->format('M d, g:i A') }}
                            </p>
                        </div>
                    </div>

                    {{-- Replies --}}
                    @foreach($selected->replies as $reply)
                        @if($reply->isFromStaff())
                            {{-- Staff reply — left, green --}}
                            <div class="flex justify-start">
                                <div class="max-w-[72%]">
                                    <div class="support-message support-message--staff rounded-2xl rounded-tl-sm bg-[#00491E] px-4 py-2.5 shadow-sm">
                                        <p class="text-sm text-white whitespace-pre-wrap leading-relaxed">{{ $reply->message }}</p>
                                    </div>
                                    <p class="support-message__meta mt-1 text-[11px] text-gray-400">
                                        {{ $reply->sender?->name ?? 'Staff' }} &bull; {{ $reply->created_at->format('M d, g:i A') }}
                                    </p>
                                </div>
                            </div>
                        @else
                            {{-- Guest reply — right, light --}}
                            <div class="flex justify-end">
                                <div class="max-w-[72%]">
                                    <div class="support-message support-message--guest rounded-2xl rounded-tr-sm bg-gray-100 px-4 py-2.5">
                                        <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $reply->message }}</p>
                                    </div>
                                    <p class="support-message__meta mt-1 text-right text-[11px] text-gray-400">
                                        {{ $selected->name }} &bull; {{ $reply->created_at->format('M d, g:i A') }}
                                    </p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Reply input --}}
                @if($selected->guest_account_id && $this->canReply())
                    <div class="support-inbox__composer border-t border-gray-200 bg-white px-4 py-3">
                        @error('replyText')
                            <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <div class="flex items-end gap-3">
                            <textarea wire:model="replyText"
                                      rows="2"
                                      placeholder="Reply to {{ $selected->name }}..."
                                      class="flex-1 resize-none rounded-xl border-gray-300 text-sm focus:border-[#00491E] focus:ring-[#00491E]"
                                      wire:keydown.ctrl.enter="sendReply"
                                      wire:keydown.meta.enter="sendReply"></textarea>
                            <button wire:click="sendReply"
                                    wire:loading.attr="disabled"
                                    wire:target="sendReply"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-[#00491E] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#02681E] disabled:opacity-50 transition-opacity">
                                <span wire:loading.remove wire:target="sendReply">
                                    <svg class="h-4 w-4 rotate-90" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                                    </svg>
                                </span>
                                <span wire:loading wire:target="sendReply">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                    </svg>
                                </span>
                                <span class="hidden sm:inline" wire:loading.remove wire:target="sendReply">Send</span>
                            </button>
                        </div>
                        <p class="mt-1.5 text-[11px] text-gray-400">Ctrl + Enter to send</p>
                    </div>
                @elseif(!$selected->guest_account_id)
                    <div class="border-t border-gray-200 bg-gray-50 px-5 py-4 text-center text-sm text-gray-400">
                        This inquiry is from an unregistered visitor — no guest account to reply to.
                    </div>
                @else
                    <div class="border-t border-gray-200 bg-gray-50 px-5 py-4 text-center text-sm text-gray-400">
                        You have read-only access to support inquiries.
                    </div>
                @endif

            @else
                {{-- Empty state --}}
                <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <svg class="h-8 w-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-500">No conversation selected</p>
                        <p class="text-sm text-gray-400">Pick a thread from the list to view and reply.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
