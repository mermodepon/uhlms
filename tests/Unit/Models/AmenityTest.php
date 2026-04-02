<?php

namespace Tests\Unit\Models;

use App\Models\Amenity;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmenityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $amenity = new Amenity;
        $this->assertEquals(['name', 'description', 'is_active'], $amenity->getFillable());
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $amenity = Amenity::create(['name' => 'WiFi', 'is_active' => 1]);

        $this->assertIsBool($amenity->is_active);
        $this->assertTrue($amenity->is_active);
    }

    public function test_room_types_relationship(): void
    {
        $amenity = Amenity::create(['name' => 'Pool', 'is_active' => true]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $amenity->roomTypes());
    }

    public function test_room_types_pivot_has_timestamps(): void
    {
        $amenity = Amenity::create(['name' => 'Gym', 'is_active' => true]);
        $roomType = RoomType::create([
            'name' => 'Suite',
            'base_rate' => 100,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $amenity->roomTypes()->attach($roomType->id);

        $pivot = $amenity->roomTypes()->first()->pivot;
        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
    }
}
