<?php

namespace Tests\Unit\Models;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\RoomAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $guest = new Guest;
        $fillable = $guest->getFillable();

        $this->assertContains('reservation_id', $fillable);
        $this->assertContains('full_name', $fillable);
        $this->assertContains('first_name', $fillable);
        $this->assertContains('last_name', $fillable);
        $this->assertContains('gender', $fillable);
        $this->assertContains('age', $fillable);
    }

    public function test_age_cast_to_integer(): void
    {
        $guest = new Guest;
        $casts = $guest->getCasts();

        $this->assertEquals('integer', $casts['age']);
    }

    public function test_reservation_relationship(): void
    {
        $guest = new Guest;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $guest->reservation()
        );
    }

    public function test_room_assignments_relationship(): void
    {
        $guest = new Guest;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $guest->roomAssignments()
        );
    }
}
