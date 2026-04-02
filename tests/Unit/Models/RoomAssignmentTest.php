<?php

namespace Tests\Unit\Models;

use App\Models\RoomAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $assignment = new RoomAssignment;
        $fillable = $assignment->getFillable();

        $this->assertContains('reservation_id', $fillable);
        $this->assertContains('guest_id', $fillable);
        $this->assertContains('room_id', $fillable);
        $this->assertContains('assigned_by', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('checked_in_at', $fillable);
        $this->assertContains('checked_out_at', $fillable);
        $this->assertContains('guest_first_name', $fillable);
        $this->assertContains('guest_last_name', $fillable);
        $this->assertContains('is_student', $fillable);
        $this->assertContains('is_senior_citizen', $fillable);
        $this->assertContains('is_pwd', $fillable);
    }

    public function test_casts(): void
    {
        $assignment = new RoomAssignment;
        $casts = $assignment->getCasts();

        $this->assertEquals('datetime', $casts['assigned_at']);
        $this->assertEquals('datetime', $casts['checked_in_at']);
        $this->assertEquals('datetime', $casts['checked_out_at']);
        $this->assertEquals('boolean', $casts['is_student']);
        $this->assertEquals('boolean', $casts['is_senior_citizen']);
        $this->assertEquals('boolean', $casts['is_pwd']);
        $this->assertEquals('integer', $casts['guest_age']);
        $this->assertEquals('array', $casts['additional_requests']);
        $this->assertEquals('decimal:2', $casts['payment_amount']);
    }

    public function test_reservation_relationship(): void
    {
        $assignment = new RoomAssignment;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $assignment->reservation()
        );
    }

    public function test_guest_relationship(): void
    {
        $assignment = new RoomAssignment;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $assignment->guest()
        );
    }

    public function test_room_relationship(): void
    {
        $assignment = new RoomAssignment;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $assignment->room()
        );
    }

    public function test_assigned_by_user_relationship(): void
    {
        $assignment = new RoomAssignment;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $assignment->assignedByUser()
        );
    }

    public function test_checked_out_by_user_relationship(): void
    {
        $assignment = new RoomAssignment;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $assignment->checkedOutByUser()
        );
    }
}
