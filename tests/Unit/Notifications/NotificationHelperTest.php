<?php

namespace Tests\Unit\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\NotificationHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NotificationHelperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cached staff users
        Cache::forget('system.staff_users');
    }

    private function createUser(string $role, string $email = null): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $email ?? $role . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    public function test_notify_all_staff_creates_notifications_for_all_staff(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        NotificationHelper::notifyAllStaff(
            'Test Notification',
            'This is a test.',
            'info',
            'test',
            '/test/url'
        );

        $this->assertEquals(3, Notification::count());

        // Each user should have one notification
        foreach ([$superAdmin, $admin, $staff] as $user) {
            $notification = Notification::where('notifiable_id', $user->id)
                ->where('notifiable_type', User::class)
                ->first();

            $this->assertNotNull($notification);
            $this->assertEquals('Test Notification', $notification->title);
            $this->assertEquals('info', $notification->type);
            $this->assertEquals('test', $notification->category);
        }
    }

    public function test_notify_user_creates_single_notification(): void
    {
        $user = $this->createUser('admin');

        $notification = NotificationHelper::notifyUser(
            $user,
            'Personal Alert',
            'Just for you.',
            'warning',
            'personal',
            '/personal/url'
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals('Personal Alert', $notification->title);
        $this->assertEquals($user->id, $notification->notifiable_id);
        $this->assertEquals('warning', $notification->type);
    }

    public function test_notify_users_creates_notifications_for_specified_ids(): void
    {
        $user1 = $this->createUser('admin');
        $user2 = $this->createUser('staff');
        $user3 = $this->createUser('staff'); // not included

        NotificationHelper::notifyUsers(
            [$user1->id, $user2->id],
            'Group Alert',
            'For selected users.',
            'danger'
        );

        $this->assertEquals(2, Notification::count());
        $this->assertNotNull(Notification::where('notifiable_id', $user1->id)->first());
        $this->assertNotNull(Notification::where('notifiable_id', $user2->id)->first());
        $this->assertNull(Notification::where('notifiable_id', $user3->id)->first());
    }

    public function test_staff_users_are_cached(): void
    {
        $this->createUser('admin');
        $this->createUser('staff');

        // First call should cache
        NotificationHelper::notifyAllStaff('Test', 'Test msg');

        $this->assertNotNull(Cache::get('system.staff_users'));
    }
}
