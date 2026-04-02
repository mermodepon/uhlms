<?php

namespace Tests\Unit\Livewire;

use App\Livewire\NotificationDropdown;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationDropdownTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    private function createNotification(bool $isRead = false): Notification
    {
        return Notification::create([
            'title' => 'Test Notification',
            'message' => 'Test message',
            'type' => 'info',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'is_read' => $isRead,
        ]);
    }

    public function test_component_renders(): void
    {
        $this->actingAs($this->user);

        Livewire::test(NotificationDropdown::class)
            ->assertOk();
    }

    public function test_loads_unread_count_on_mount(): void
    {
        $this->createNotification(false);
        $this->createNotification(false);
        $this->createNotification(true);

        $this->actingAs($this->user);

        Livewire::test(NotificationDropdown::class)
            ->assertSet('unreadCount', 2);
    }

    public function test_toggle_dropdown_opens_and_marks_all_read(): void
    {
        $this->createNotification(false);
        $this->createNotification(false);

        $this->actingAs($this->user);

        Livewire::test(NotificationDropdown::class)
            ->call('toggleDropdown')
            ->assertSet('showDropdown', true)
            ->assertSet('unreadCount', 0);
    }

    public function test_toggle_dropdown_closes(): void
    {
        $this->actingAs($this->user);

        Livewire::test(NotificationDropdown::class)
            ->call('toggleDropdown') // open
            ->call('toggleDropdown') // close
            ->assertSet('showDropdown', false);
    }

    public function test_mark_as_read(): void
    {
        $notification = $this->createNotification(false);

        $this->actingAs($this->user);

        Livewire::test(NotificationDropdown::class)
            ->call('markAsRead', $notification->id);

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_mark_all_as_read(): void
    {
        $this->createNotification(false);
        $this->createNotification(false);

        $this->actingAs($this->user);

        Livewire::test(NotificationDropdown::class)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);

        $unread = Notification::where('notifiable_id', $this->user->id)
            ->where('is_read', false)
            ->count();

        $this->assertEquals(0, $unread);
    }

    public function test_delete_notification(): void
    {
        $notification = $this->createNotification(false);

        $this->actingAs($this->user);

        Livewire::test(NotificationDropdown::class)
            ->call('deleteNotification', $notification->id);

        $this->assertNull(Notification::find($notification->id));
    }

    public function test_unauthenticated_user_has_zero_count(): void
    {
        Livewire::test(NotificationDropdown::class)
            ->assertSet('unreadCount', 0);
    }

    public function test_check_for_new_notifications(): void
    {
        $this->createNotification(false);

        $this->actingAs($this->user);

        Livewire::test(NotificationDropdown::class)
            ->call('checkForNewNotifications')
            ->assertSet('unreadCount', 1);
    }
}
