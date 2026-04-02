<?php

namespace Tests\Unit\Models;

use App\Models\ReservationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $log = new ReservationLog;
        $fillable = $log->getFillable();

        $this->assertContains('reservation_id', $fillable);
        $this->assertContains('event', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('actor_id', $fillable);
        $this->assertContains('actor_name', $fillable);
        $this->assertContains('meta', $fillable);
        $this->assertContains('logged_at', $fillable);
    }

    public function test_casts(): void
    {
        $log = new ReservationLog;
        $casts = $log->getCasts();

        $this->assertEquals('array', $casts['meta']);
        $this->assertEquals('datetime', $casts['logged_at']);
    }

    public function test_reservation_relationship(): void
    {
        $log = new ReservationLog;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $log->reservation()
        );
    }

    public function test_actor_relationship(): void
    {
        $log = new ReservationLog;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $log->actor()
        );
    }

    public function test_event_label_known_events(): void
    {
        $this->assertEquals('Reservation Created', ReservationLog::eventLabel('reservation_created'));
        $this->assertEquals('Approved', ReservationLog::eventLabel('reservation_approved'));
        $this->assertEquals('Declined', ReservationLog::eventLabel('reservation_declined'));
        $this->assertEquals('Cancelled', ReservationLog::eventLabel('reservation_cancelled'));
        $this->assertEquals('Checked Out', ReservationLog::eventLabel('reservation_checked_out'));
        $this->assertEquals('Hold Prepared', ReservationLog::eventLabel('checkin_hold_prepared'));
        $this->assertEquals('Hold Released', ReservationLog::eventLabel('checkin_hold_released'));
        $this->assertEquals('Hold Expired', ReservationLog::eventLabel('checkin_hold_expired'));
        $this->assertEquals('Check-in Finalized', ReservationLog::eventLabel('checkin_finalized'));
        $this->assertEquals('Guest Checked In', ReservationLog::eventLabel('guest_checked_in'));
        $this->assertEquals('Guest Checked Out', ReservationLog::eventLabel('guest_checked_out'));
        $this->assertEquals('Assignment Removed', ReservationLog::eventLabel('room_assignment_removed'));
    }

    public function test_event_label_unknown_event(): void
    {
        $this->assertEquals('Some Custom Event', ReservationLog::eventLabel('some_custom_event'));
    }

    public function test_event_color_known_events(): void
    {
        $this->assertEquals('info', ReservationLog::eventColor('reservation_created'));
        $this->assertEquals('success', ReservationLog::eventColor('reservation_approved'));
        $this->assertEquals('danger', ReservationLog::eventColor('reservation_declined'));
        $this->assertEquals('danger', ReservationLog::eventColor('reservation_cancelled'));
        $this->assertEquals('gray', ReservationLog::eventColor('reservation_checked_out'));
        $this->assertEquals('warning', ReservationLog::eventColor('checkin_hold_prepared'));
        $this->assertEquals('gray', ReservationLog::eventColor('checkin_hold_released'));
        $this->assertEquals('warning', ReservationLog::eventColor('checkin_hold_expired'));
        $this->assertEquals('success', ReservationLog::eventColor('checkin_finalized'));
        $this->assertEquals('info', ReservationLog::eventColor('guest_checked_in'));
        $this->assertEquals('gray', ReservationLog::eventColor('guest_checked_out'));
        $this->assertEquals('warning', ReservationLog::eventColor('room_assignment_removed'));
    }

    public function test_event_color_unknown_event(): void
    {
        $this->assertEquals('gray', ReservationLog::eventColor('unknown_event'));
    }
}
