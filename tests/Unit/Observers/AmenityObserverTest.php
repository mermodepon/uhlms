<?php

namespace Tests\Unit\Observers;

use App\Models\Amenity;
use App\Models\Notification;
use App\Models\User;
use App\Observers\AmenityObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_created_event_notifies_all_staff(): void
    {
        $amenity = Amenity::create(['name' => 'Pool', 'is_active' => true]);

        $notification = Notification::where('title', 'New Amenity Added')
            ->where('message', 'like', '%Pool%')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('success', $notification->type);
        $this->assertEquals('amenity', $notification->category);
    }

    public function test_updated_active_status_notifies_staff(): void
    {
        $amenity = Amenity::create(['name' => 'Gym', 'is_active' => true]);

        // Clear initial notifications
        Notification::query()->delete();

        $amenity->update(['is_active' => false]);

        $notification = Notification::where('title', 'Amenity Deactivated')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('warning', $notification->type);
    }

    public function test_updated_other_fields_notifies_staff(): void
    {
        $amenity = Amenity::create(['name' => 'Sauna', 'is_active' => true]);
        Notification::query()->delete();

        $amenity->update(['description' => 'Hot sauna']);

        $notification = Notification::where('title', 'Amenity Updated')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('info', $notification->type);
    }

    public function test_deleted_event_notifies_staff(): void
    {
        $amenity = Amenity::create(['name' => 'Spa', 'is_active' => true]);
        Notification::query()->delete();

        $amenity->delete();

        $notification = Notification::where('title', 'Amenity Deleted')->first();
        $this->assertNotNull($notification);
        $this->assertEquals('danger', $notification->type);
    }
}
