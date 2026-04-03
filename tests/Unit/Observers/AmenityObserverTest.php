<?php

namespace Tests\Unit\Observers;

use App\Models\Amenity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AmenityObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create staff user for notifications
        User::create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    protected function findNotificationByTitle(string $title): ?object
    {
        return DB::table('notifications')
            ->where('data', 'like', '%"title":"'.$title.'"%')
            ->first();
    }

    protected function clearNotifications(): void
    {
        DB::table('notifications')->delete();
    }

    public function test_created_event_notifies_all_staff(): void
    {
        $amenity = Amenity::create(['name' => 'Pool', 'is_active' => true]);

        $notification = $this->findNotificationByTitle('New Amenity Added');
        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertStringContainsString('Pool', $data['body']);
    }

    public function test_updated_active_status_notifies_staff(): void
    {
        $amenity = Amenity::create(['name' => 'Gym', 'is_active' => true]);

        // Clear initial notifications
        $this->clearNotifications();

        $amenity->update(['is_active' => false]);

        $notification = $this->findNotificationByTitle('Amenity Deactivated');
        $this->assertNotNull($notification);
    }

    public function test_updated_other_fields_notifies_staff(): void
    {
        $amenity = Amenity::create(['name' => 'Sauna', 'is_active' => true]);
        $this->clearNotifications();

        $amenity->update(['description' => 'Hot sauna']);

        $notification = $this->findNotificationByTitle('Amenity Updated');
        $this->assertNotNull($notification);
    }

    public function test_deleted_event_notifies_staff(): void
    {
        $amenity = Amenity::create(['name' => 'Spa', 'is_active' => true]);
        $this->clearNotifications();

        $amenity->delete();

        $notification = $this->findNotificationByTitle('Amenity Deleted');
        $this->assertNotNull($notification);
    }
}
