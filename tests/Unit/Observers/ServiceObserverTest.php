<?php

namespace Tests\Unit\Observers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    protected function findNotificationByTitle(string $title): ?object
    {
        return DB::table('notifications')
            ->where('data', 'like', '%"title":"' . $title . '"%')
            ->first();
    }

    protected function clearNotifications(): void
    {
        DB::table('notifications')->delete();
    }

    public function test_created_event_notifies_staff(): void
    {
        $this->clearNotifications();

        $service = Service::create(['name' => 'Extra Towels', 'price' => 50, 'is_active' => true]);

        $notification = $this->findNotificationByTitle('New Add-On Created');
        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertStringContainsString('Extra Towels', $data['body']);
    }

    public function test_updated_active_status_deactivated_notifies_staff(): void
    {
        $service = Service::create(['name' => 'Laundry', 'price' => 100, 'is_active' => true]);
        $this->clearNotifications();

        $service->update(['is_active' => false]);

        $notification = $this->findNotificationByTitle('Add-On Deactivated');
        $this->assertNotNull($notification);
    }

    public function test_updated_active_status_activated_notifies_staff(): void
    {
        $service = Service::create(['name' => 'Mini Bar', 'price' => 200, 'is_active' => false]);
        $this->clearNotifications();

        $service->update(['is_active' => true]);

        $notification = $this->findNotificationByTitle('Add-On Activated');
        $this->assertNotNull($notification);
    }

    public function test_updated_other_fields_notifies_staff(): void
    {
        $service = Service::create(['name' => 'WiFi', 'price' => 0, 'is_active' => true]);
        $this->clearNotifications();

        $service->update(['description' => 'High-speed internet access']);

        $notification = $this->findNotificationByTitle('Add-On Updated');
        $this->assertNotNull($notification);
    }

    public function test_deleted_event_notifies_staff(): void
    {
        $service = Service::create(['name' => 'Room Service', 'price' => 150, 'is_active' => true]);
        $this->clearNotifications();

        $service->delete();

        $notification = $this->findNotificationByTitle('Add-On Deleted');
        $this->assertNotNull($notification);
    }
}
