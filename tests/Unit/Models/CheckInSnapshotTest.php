<?php

namespace Tests\Unit\Models;

use App\Models\CheckInSnapshot;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $snapshot = new CheckInSnapshot;
        $this->assertContains('reservation_id', $snapshot->getFillable());
        $this->assertContains('guest_id', $snapshot->getFillable());
        $this->assertContains('payment_amount', $snapshot->getFillable());
        $this->assertContains('id_type', $snapshot->getFillable());
    }

    public function test_casts(): void
    {
        $snapshot = new CheckInSnapshot;
        $casts = $snapshot->getCasts();

        $this->assertEquals('datetime', $casts['detailed_checkin_datetime']);
        $this->assertEquals('datetime', $casts['detailed_checkout_datetime']);
        $this->assertEquals('decimal:2', $casts['payment_amount']);
        $this->assertEquals('array', $casts['additional_requests']);
        $this->assertEquals('datetime', $casts['captured_at']);
    }

    public function test_reservation_relationship(): void
    {
        $snapshot = new CheckInSnapshot;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $snapshot->reservation()
        );
    }

    public function test_guest_relationship(): void
    {
        $snapshot = new CheckInSnapshot;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $snapshot->guest()
        );
    }

    public function test_captured_by_user_relationship(): void
    {
        $snapshot = new CheckInSnapshot;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $snapshot->capturedByUser()
        );
    }
}
