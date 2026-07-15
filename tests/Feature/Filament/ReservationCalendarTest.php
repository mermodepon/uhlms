<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\ReservationCalendar;
use App\Models\Reservation;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_reservations_use_confirmed_calendar_color(): void
    {
        $roomType = RoomType::create([
            'name' => 'Deluxe',
            'base_rate' => 1200,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'reference_number' => '2026-0039',
            'guest_first_name' => 'Rommel',
            'guest_last_name' => 'Fajardo',
            'guest_email' => 'rommel@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => '2026-05-02',
            'check_out_date' => '2026-05-08',
            'number_of_occupants' => 2,
            'status' => 'confirmed',
        ]);

        $events = (new ReservationCalendar)->fetchEvents([
            'start' => Carbon::parse('2026-05-01')->toDateString(),
            'end' => Carbon::parse('2026-05-31')->toDateString(),
        ]);

        $event = collect($events)->firstWhere('id', $reservation->id);

        $this->assertNotNull($event);
        $this->assertSame('#10B981', $event['backgroundColor']);
        $this->assertNotSame('#d1d5db', $event['backgroundColor']);
    }

    public function test_approved_reservations_use_theme_adjacent_calendar_color(): void
    {
        $roomType = RoomType::create([
            'name' => 'Standard',
            'base_rate' => 800,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'reference_number' => '2026-0040',
            'guest_first_name' => 'Maria',
            'guest_last_name' => 'Santos',
            'guest_email' => 'maria@example.com',
            'guest_phone' => '09171234568',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => '2026-05-10',
            'check_out_date' => '2026-05-12',
            'number_of_occupants' => 1,
            'status' => 'approved',
        ]);

        $events = (new ReservationCalendar)->fetchEvents([
            'start' => Carbon::parse('2026-05-01')->toDateString(),
            'end' => Carbon::parse('2026-05-31')->toDateString(),
        ]);

        $event = collect($events)->firstWhere('id', $reservation->id);

        $this->assertNotNull($event);
        $this->assertSame('#919F02', $event['backgroundColor']);
    }

    public function test_confirmed_status_is_available_in_calendar_legend_and_admin_filters(): void
    {
        $this->assertStringContainsString(
            "'key' => 'confirmed'",
            file_get_contents(resource_path('views/filament/widgets/reservation-calendar.blade.php'))
        );

        $this->assertSame('Confirmed', Reservation::statusOptions()['confirmed']);
        $this->assertStringContainsString(
            '<option value="confirmed">Confirmed</option>',
            file_get_contents(resource_path('views/filament/pages/reports.blade.php'))
        );
    }
}
