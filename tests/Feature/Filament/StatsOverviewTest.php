<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\StatsOverview;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RoomType $roomType;

    private Floor $floor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-28 10:00:00'));
        Cache::flush();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'permissions' => null,
        ]);

        $this->roomType = RoomType::create([
            'name' => 'Standard',
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $this->floor = Floor::create([
            'name' => 'Ground Floor',
            'level' => 1,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_splits_approved_and_confirmed_status_cards(): void
    {
        $this->actingAs($this->admin);

        $this->createReservation('pending', '2026-05-29', '2026-05-30');
        $this->createReservation('approved', '2026-05-28', '2026-05-30');
        $this->createReservation('approved', '2026-05-29', '2026-05-31');
        $this->createReservation('confirmed', '2026-05-28', '2026-05-30');
        $this->createReservation('confirmed', '2026-05-28', '2026-05-31');
        $this->createReservation('confirmed', '2026-05-29', '2026-06-01');

        $stats = $this->statsByLabel();

        $this->assertSame(1, $stats['Pending Reservations']->getValue());
        $this->assertSame(2, $stats['Approved']->getValue());
        $this->assertSame('Awaiting payment or confirmation', $stats['Approved']->getDescription());
        $this->assertStringContainsString('status=approved', $stats['Approved']->getUrl());

        $this->assertSame(3, $stats['Confirmed (Awaiting Arrival)']->getValue());
        $this->assertSame('2 expected check-ins today', $stats['Confirmed (Awaiting Arrival)']->getDescription());
        $this->assertStringContainsString('status=confirmed', $stats['Confirmed (Awaiting Arrival)']->getUrl());
    }

    public function test_dashboard_keeps_checked_in_near_due_and_overdue_counts(): void
    {
        $this->actingAs($this->admin);

        $nearDueToday = $this->createReservation('checked_in', '2026-05-26', '2026-05-28');
        $nearDueTomorrow = $this->createReservation('checked_in', '2026-05-27', '2026-05-29');
        $overdue = $this->createReservation('checked_in', '2026-05-24', '2026-05-27');

        $this->createOpenAssignment($nearDueToday, '101');
        $this->createOpenAssignment($nearDueTomorrow, '102');
        $this->createOpenAssignment($overdue, '103');

        $stats = $this->statsByLabel();

        $this->assertSame(2, $stats['Near Due']->getValue());
        $this->assertSame(1, $stats['Overdue Check-outs']->getValue());
        $this->assertSame(3, $stats['Currently Checked In']->getValue());
        $this->assertStringContainsString('near_due=1', $stats['Near Due']->getUrl());
        $this->assertStringContainsString('overdue=1', $stats['Overdue Check-outs']->getUrl());
        $this->assertStringContainsString('status=checked_in', $stats['Currently Checked In']->getUrl());
    }

    /**
     * @return array<string, \Filament\Widgets\StatsOverviewWidget\Stat>
     */
    private function statsByLabel(): array
    {
        return collect((new TestableStatsOverview)->publicStats())
            ->keyBy(fn ($stat) => $stat->getLabel())
            ->all();
    }

    private function createReservation(string $status, string $checkIn, string $checkOut): Reservation
    {
        return Reservation::create([
            'reference_number' => uniqid('2026-', false),
            'guest_first_name' => 'Guest',
            'guest_last_name' => ucfirst($status),
            'guest_email' => uniqid('guest-', false).'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $this->roomType->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'number_of_occupants' => 1,
            'status' => $status,
        ]);
    }

    private function createOpenAssignment(Reservation $reservation, string $roomNumber): void
    {
        $room = Room::create([
            'room_number' => $roomNumber,
            'room_type_id' => $this->roomType->id,
            'floor_id' => $this->floor->id,
            'capacity' => 2,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'checked_in_at' => now()->subDay(),
            'status' => 'checked_in',
        ]);
    }
}

class TestableStatsOverview extends StatsOverview
{
    public function publicStats(): array
    {
        return $this->getStats();
    }
}
