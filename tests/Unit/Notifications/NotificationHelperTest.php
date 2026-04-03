<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\NotificationHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
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

        // Each user should have one database notification
        foreach ([$superAdmin, $admin, $staff] as $user) {
            $this->assertEquals(1, $user->notifications()->count());
        }
    }

    public function test_notify_user_creates_single_notification(): void
    {
        $user = $this->createUser('admin');

        NotificationHelper::notifyUser(
            $user,
            'Personal Alert',
            'Just for you.',
            'warning',
            'personal',
            '/personal/url'
        );

        $this->assertEquals(1, $user->notifications()->count());
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

        $this->assertEquals(1, $user1->notifications()->count());
        $this->assertEquals(1, $user2->notifications()->count());
        $this->assertEquals(0, $user3->notifications()->count());
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
