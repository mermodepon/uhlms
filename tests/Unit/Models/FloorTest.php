<?php

namespace Tests\Unit\Models;

use App\Models\Floor;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $floor = new Floor;
        $this->assertEquals(['name', 'level', 'description', 'is_active'], $floor->getFillable());
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $floor = Floor::create(['name' => 'Ground Floor', 'level' => 1, 'is_active' => 1]);

        $this->assertIsBool($floor->is_active);
        $this->assertTrue($floor->is_active);
    }

    public function test_rooms_relationship(): void
    {
        $floor = Floor::create(['name' => 'First Floor', 'level' => 1, 'is_active' => true]);

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $floor->rooms()
        );
    }
}
