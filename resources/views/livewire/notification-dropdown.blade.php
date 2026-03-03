<div class="relative" wire:poll.5s="checkForNewNotifications">
    <!-- Dropdown Toggle Button -->
    <button 
        wire:click="toggleDropdown"
        class="relative inline-flex items-center justify-center p-2 text-white bg-orange-500 hover:bg-orange-600 rounded-lg transition shadow-md"
        title="Notifications"
    >
        <!-- Bell Icon -->
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        
        <!-- Unread Count Badge -->
        @if($unreadCount > 0)
            <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full shadow-lg">
                {{ $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown Panel -->
    @if($showDropdown)
        <!-- Backdrop -->
        <div class="fixed inset-0 z-[9998]" wire:click="$set('showDropdown', false)"></div>
        
        <div class="fixed w-96 bg-white rounded-lg shadow-2xl z-[9999] max-h-96 border border-gray-200" style="right: 1.5rem; top: 4.5rem; max-height: 32rem;">
            <style>
                .notification-dropdown-content {
                    overflow-y: auto;
                    max-height: calc(32rem - 120px);
                }
            </style>
            <!-- Header -->
            <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-900">Notifications</h3>
                @if($unreadCount > 0)
                    <button 
                        wire:click="markAllAsRead"
                        class="text-xs text-orange-600 hover:text-orange-700 font-semibold"
                    >
                        Mark all as read
                    </button>
                @endif
            </div>

            <!-- Notifications List with Scroll -->
            <div class="notification-dropdown-content">
                @if($isLoading)
                    <!-- Loading Spinner -->
                    <div class="flex items-center justify-center h-64">
                        <div class="flex flex-col items-center gap-3">
                            <svg class="animate-spin h-12 w-12 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-sm text-gray-500 font-medium">Loading notifications...</p>
                        </div>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @forelse($notifications as $notification)
                            <div 
                                @class([
                                    'p-4 hover:bg-gray-50 transition border-l-4',
                                    'bg-orange-50 border-l-orange-500' => !$notification['is_read'],
                                    'bg-white border-l-gray-200' => $notification['is_read'],
                                ])
                            >
                                <div class="flex gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-gray-900 text-sm">{{ $notification['title'] }}</p>
                                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ $notification['message'] }}</p>
                                                <div class="mt-2 flex items-center justify-between">
                                                    <p class="text-xs text-gray-400">{{ $notification['created_at'] }}</p>
                                                    <p class="text-xs text-orange-600 font-medium">by {{ $notification['created_by_name'] }}</p>
                                                </div>
                                            </div>
                                            <button 
                                                wire:click="deleteNotification({{ $notification['id'] }})"
                                                class="flex-shrink-0 text-gray-400 hover:text-gray-600 ml-2"
                                            >
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <p class="text-sm font-medium">No notifications yet</p>
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-200 text-center bg-gray-50">
                <a href="/admin/notifications" class="text-orange-600 hover:text-orange-700 text-sm font-semibold inline-flex items-center gap-1">
                    View all notifications
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    @endif
</div>
