<?php

namespace Tests\Unit\Models;

use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTypeTest extends TestCase
{
    use RefreshDatabase;

    private function createRoomType(array $overrides = []): RoomType
    {
        return RoomType::create(array_merge([
            'name' => 'Standard Room',
            'base_rate' => 1000,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ], $overrides));
    }

    public function test_fillable_attributes(): void
    {
        $roomType = new RoomType;
        $fillable = $roomType->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('base_rate', $fillable);
        $this->assertContains('pricing_type', $fillable);
        $this->assertContains('room_sharing_type', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertContains('images', $fillable);
    }

    public function test_casts(): void
    {
        $roomType = new RoomType;
        $casts = $roomType->getCasts();

        $this->assertEquals('array', $casts['images']);
        $this->assertEquals('decimal:2', $casts['base_rate']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_rooms_relationship(): void
    {
        $roomType = new RoomType;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $roomType->rooms()
        );
    }

    public function test_amenities_relationship(): void
    {
        $roomType = new RoomType;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
            $roomType->amenities()
        );
    }

    public function test_reservations_relationship(): void
    {
        $roomType = new RoomType;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $roomType->reservations()
        );
    }

    public function test_is_per_person_pricing(): void
    {
        $roomType = $this->createRoomType(['pricing_type' => 'per_person']);
        $this->assertTrue($roomType->isPerPersonPricing());

        $roomType2 = $this->createRoomType(['pricing_type' => 'flat_rate', 'name' => 'Another']);
        $this->assertFalse($roomType2->isPerPersonPricing());
    }

    public function test_is_private(): void
    {
        $private = $this->createRoomType(['room_sharing_type' => 'private']);
        $this->assertTrue($private->isPrivate());

        $public = $this->createRoomType(['room_sharing_type' => 'public', 'name' => 'Dorm']);
        $this->assertFalse($public->isPrivate());
    }

    public function test_is_public(): void
    {
        $public = $this->createRoomType(['room_sharing_type' => 'public']);
        $this->assertTrue($public->isPublic());

        $private = $this->createRoomType(['room_sharing_type' => 'private', 'name' => 'Suite']);
        $this->assertFalse($private->isPublic());
    }

    public function test_formatted_price_flat_rate(): void
    {
        $roomType = $this->createRoomType(['base_rate' => 1500, 'pricing_type' => 'flat_rate']);
        $this->assertEquals('₱1,500/night', $roomType->getFormattedPrice());
    }

    public function test_formatted_price_per_person(): void
    {
        $roomType = $this->createRoomType(['base_rate' => 500, 'pricing_type' => 'per_person']);
        $this->assertEquals('₱500/person/night', $roomType->getFormattedPrice());
    }

    public function test_calculate_rate_flat_rate(): void
    {
        $roomType = $this->createRoomType(['base_rate' => 1000, 'pricing_type' => 'flat_rate']);

        $this->assertEquals(1000, $roomType->calculateRate(1, 1));
        $this->assertEquals(3000, $roomType->calculateRate(3, 1));
        // guests param is ignored for flat_rate
        $this->assertEquals(3000, $roomType->calculateRate(3, 5));
    }

    public function test_calculate_rate_per_person(): void
    {
        $roomType = $this->createRoomType(['base_rate' => 500, 'pricing_type' => 'per_person']);

        $this->assertEquals(500, $roomType->calculateRate(1, 1));
        $this->assertEquals(1500, $roomType->calculateRate(3, 1));
        $this->assertEquals(3000, $roomType->calculateRate(3, 2));
    }

    public function test_available_rooms_scope(): void
    {
        $roomType = $this->createRoomType();
        $floor = \App\Models\Floor::create(['name' => 'F1', 'level' => 1, 'is_active' => true]);

        Room::create([
            'room_number' => '101',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);

        Room::create([
            'room_number' => '102',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        Room::create([
            'room_number' => '103',
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'available',
            'is_active' => false,
        ]);

        $this->assertEquals(1, $roomType->availableRooms()->count());
    }
}
