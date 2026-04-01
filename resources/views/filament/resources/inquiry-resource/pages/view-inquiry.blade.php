<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Reservation Details Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Reservation Details
                </h3>
                <span class="px-3 py-1 text-xs font-semibold rounded-full" 
                    style="background-color: {{ 
                        match($record->status) {
                            'pending' => 'rgb(245, 158, 11)',
                            'approved' => 'rgb(59, 130, 246)',
                            'declined' => 'rgb(239, 68, 68)',
                            'cancelled' => 'rgb(156, 163, 175)',
                            'checked_in' => 'rgb(34, 197, 94)',
                            'checked_out' => 'rgb(107, 114, 128)',
                            default => 'rgb(156, 163, 175)'
                        }
                    }}; color: white;">
                    {{ ucfirst($record->status) }}
                </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Reference:</span>
                    <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $record->reference_number }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Guest:</span>
                    <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $record->guest_name }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Check-in:</span>
                    <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $record->check_in_date->format('M d, Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Messages Container --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            {{-- Messages Header --}}
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Conversation
                </h3>
            </div>

            {{-- Messages List --}}
            <div 
                wire:poll.10s
                class="p-4 space-y-4 overflow-y-auto" 
                style="max-height: 600px;"
                x-data="{ 
                    scrollToBottom() {
                        this.$el.scrollTop = this.$el.scrollHeight;
                    }
                }"
                x-init="scrollToBottom()"
                x-effect="scrollToBottom()"
            >
                @forelse($record->messages()->with('sender')->orderBy('created_at')->get() as $message)
                    <div class="flex {{ $message->sender_type === 'guest' ? 'justify-start' : 'justify-end' }} group">
                        <div class="relative max-w-[70%]">
                            {{-- Delete Button (Hover) --}}
                            <button 
                                wire:click="deleteMessage({{ $message->id }})"
                                wire:confirm="Are you sure you want to delete this message?"
                                class="absolute -left-8 top-2 opacity-0 group-hover:opacity-100 transition-opacity text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                title="Delete message"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>

                            {{-- Message Bubble --}}
                            <div class="rounded-lg px-4 py-3" style="{{ 
                                $message->sender_type === 'guest' 
                                    ? 'background-color: #3b82f6;' 
                                    : 'background-color: #22c55e;' 
                            }}">
                                <p class="text-sm break-words whitespace-pre-wrap" style="color: white !important;">{{ $message->message }}</p>
                            </div>

                            {{-- Message Meta Info --}}
                            <div class="mt-1 px-2 flex items-center gap-2 text-xs {{ 
                                $message->sender_type === 'guest' 
                                    ? 'text-gray-600 dark:text-gray-400' 
                                    : 'text-gray-600 dark:text-gray-400 justify-end' 
                            }}">
                                <span class="flex items-center gap-1">
                                    @if($message->sender)
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $message->sender->name }}
                                    @else
                                        {{ $message->sender_name ?? 'Guest' }}
                                    @endif
                                </span>
                                <span>&bull;</span>
                                <span>{{ $message->created_at->diffForHumans() }}</span>
                                @if($message->is_read)
                                    <span>&bull;</span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Read
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-lg font-medium">No messages yet</p>
                        <p class="text-sm mt-1">Start the conversation with the guest</p>
                    </div>
                @endforelse
            </div>

            {{-- Quick Reply Form --}}
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <form wire:submit.prevent="sendQuickReply" class="flex gap-3">
                    <div class="flex-1">
                        <textarea 
                            wire:model="quickReply"
                            rows="2"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Type your reply here..."
                        ></textarea>
                    </div>
                    <div class="flex items-end">
                        <button 
                            type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition font-medium"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
