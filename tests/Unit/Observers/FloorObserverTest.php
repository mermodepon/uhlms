<?php

namespace Tests\Unit\Observers;

use App\Models\Floor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FloorObserverTest extends TestCase
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

        $floor = Floor::create(['name' => 'Ground Floor', 'level' => 1, 'is_active' => true]);

        $notification = $this->findNotificationByTitle('New Floor Created');
        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertStringContainsString('Ground Floor', $data['body']);
    }

    public function test_updated_active_status_notifies_staff(): void
    {
        $floor = Floor::create(['name' => '2nd Floor', 'level' => 2, 'is_active' => true]);
        $this->clearNotifications();

        $floor->update(['is_active' => false]);

        $notification = $this->findNotificationByTitle('Floor Deactivated');
        $this->assertNotNull($notification);
    }

    public function test_updated_active_to_true_notifies_staff(): void
    {
        $floor = Floor::create(['name' => '3rd Floor', 'level' => 3, 'is_active' => false]);
        $this->clearNotifications();

        $floor->update(['is_active' => true]);

        $notification = $this->findNotificationByTitle('Floor Activated');
        $this->assertNotNull($notification);
    }

    public function test_updated_other_fields_notifies_staff(): void
    {
        $floor = Floor::create(['name' => 'Basement', 'level' => 0, 'is_active' => true]);
        $this->clearNotifications();

        $floor->update(['description' => 'Underground parking level']);

        $notification = $this->findNotificationByTitle('Floor Updated');
        $this->assertNotNull($notification);
    }

    public function test_deleted_event_notifies_staff(): void
    {
        $floor = Floor::create(['name' => 'Temp Floor', 'level' => 99, 'is_active' => true]);
        $this->clearNotifications();

        $floor->delete();

        $notification = $this->findNotificationByTitle('Floor Deleted');
        $this->assertNotNull($notification);
    }
}
