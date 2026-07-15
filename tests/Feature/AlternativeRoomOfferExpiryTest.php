<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\ReservationAlternativeOffer;
use App\Models\Room;
use App\Models\RoomHold;
use App\Models\RoomType;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlternativeRoomOfferExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }
    }

    public function test_expiry_command_releases_short_term_holds_and_returns_the_reservation_to_pending(): void
    {
        [$reservation, $offer, $hold] = $this->expiredOffer();

        $this->artisan('reservations:expire-alternative-offers')
            ->expectsOutput('Expired 1 alternative room offer(s).')
            ->assertSuccessful();

        $this->assertSame(ReservationAlternativeOffer::STATUS_EXPIRED, $offer->fresh()->status);
        $this->assertSame('pending', $reservation->fresh()->status);
        $this->assertDatabaseMissing('room_holds', ['id' => $hold->id]);
    }

    public function test_alternative_offer_expiry_command_is_registered_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'reservations:expire-alternative-offers'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    private function expiredOffer(): array
    {
        $roomType = RoomType::create([
            'name' => 'Alternative '.uniqid(),
            'base_rate' => 600,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $floor = Floor::create(['name' => 'Alternative Floor '.uniqid(), 'level' => 1, 'is_active' => true]);
        $room = Room::create([
            'room_number' => 'ALT-'.uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);
        $reservation = Reservation::create([
            'guest_first_name' => 'Alternative',
            'guest_last_name' => 'Guest',
            'guest_email' => 'alternative-'.uniqid().'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'awaiting_alternative_confirmation',
        ]);
        $offer = ReservationAlternativeOffer::create([
            'reservation_id' => $reservation->id,
            'offered_room_type_id' => $roomType->id,
            'room_ids' => [$room->id],
            'original_total' => 1000,
            'quoted_total' => 1200,
            'status' => ReservationAlternativeOffer::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);
        $hold = RoomHold::create([
            'room_id' => $room->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'short_term',
            'expires_at' => $offer->expires_at,
        ]);

        return [$reservation, $offer, $hold];
    }
}
