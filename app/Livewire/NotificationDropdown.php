<?php

namespace App\Livewire;

use App\Models\Notification;
use Livewire\Component;

class NotificationDropdown extends Component
{
    public $notifications = [];
    public $unreadCount = 0;
    public $showDropdown = false;
    public $isLoading = false;

    protected $listeners = ['notificationCreated' => 'refreshNotifications'];

    public function mount()
    {
        $this->refreshNotifications();
    }

    public function loadNotificationCounts()
    {
        $user = auth()->user();
        if (!$user) {
            $this->unreadCount = 0;
            return;
        }

        // Only load counts, not full notifications yet
        $this->unreadCount = Notification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function refreshNotifications()
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        // Load only when dropdown is opened
        if ($this->showDropdown) {
            $this->notifications = Notification::where('notifiable_type', \App\Models\User::class)
                ->where('notifiable_id', $user->id)
                ->with('creator')
                ->select('id', 'title', 'message', 'type', 'category', 'action_url', 'is_read', 'created_by', 'created_at')
                ->latest('created_at')
                ->take(10)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'category' => $notification->category,
                    'action_url' => $notification->action_url,
                    'is_read' => $notification->is_read,
                    'created_by_name' => $notification->creator?->name ?? 'System',
                    'created_at' => $notification->created_at->diffForHumans(),
                ])->toArray();
        }

        $this->loadNotificationCounts();
    }

    public function markAsRead($notificationId)
    {
        $user = auth()->user();
        if (!$user) return;

        Notification::where('id', $notificationId)
            ->where('notifiable_id', $user->id)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        
        $this->loadNotificationCounts();
    }

    public function markAllAsRead()
    {
        $user = auth()->user();
        if (!$user) return;

        Notification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        
        $this->refreshNotifications();
    }

    public function deleteNotification($notificationId)
    {
        $user = auth()->user();
        if (!$user) return;

        Notification::where('id', $notificationId)
            ->where('notifiable_id', $user->id)
            ->delete();
        
        $this->refreshNotifications();
    }

    public function toggleDropdown()
    {
        $this->showDropdown = !$this->showDropdown;
        // Load notifications only when opening the dropdown
        if ($this->showDropdown) {
            $this->isLoading = true;
            $this->refreshNotifications();
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.notification-dropdown');
    }
}
