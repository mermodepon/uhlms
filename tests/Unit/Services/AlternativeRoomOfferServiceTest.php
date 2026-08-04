<?php

namespace Tests\Unit\Services;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Services\AlternativeRoomOfferService;
use App\Services\RoomHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlternativeRoomOfferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table): void {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }
    }

    public function test_it_can_offer_an_alternative_for_a_legacy_pending_reservation(): void
    {
        $requestedType = $this->roomType('Requested');
        $requestedRoom = $this->room($requestedType, 'REQUESTED');
        $alternativeType = $this->roomType('Alternative');
        $alternativeRoom = $this->room($alternativeType, 'ALTERNATIVE');
        $reservation = $this->reservation($requestedType);

        RoomHold::create([
            'room_id' => $requestedRoom->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);

        $offer = app(AlternativeRoomOfferService::class)->propose($reservation, [
            'reservation_room_request_id' => $reservation->ensureRoomRequests()->first()->id,
            'offered_room_type_id' => $alternativeType->id,
            'room_ids' => [$alternativeRoom->id],
        ]);

        $this->assertSame('awaiting_alternative_confirmation', $reservation->fresh()->status);
        $this->assertSame([$alternativeRoom->id], $offer->room_ids);
        $this->assertDatabaseHas('room_holds', ['reservation_id' => $reservation->id, 'room_id' => $alternativeRoom->id, 'hold_type' => 'short_term']);
    }

    public function test_shared_room_alternative_must_have_space_in_the_requested_number_of_rooms(): void
    {
        $type = $this->roomType('Shared', 'public');
        $first = $this->room($type, 'SHARED-1', 3);
        $second = $this->room($type, 'SHARED-2', 3);
        $reservation = $this->reservation($type, 4);

        foreach ([$first, $second] as $room) {
            RoomHold::create([
                'room_id' => $room->id,
                'reservation_id' => $reservation->id,
                'hold_from' => $reservation->check_in_date,
                'hold_to' => $reservation->check_out_date,
                'hold_type' => 'advance',
                'held_guest_count' => 2,
            ]);
        }

        $this->assertFalse(app(RoomHoldService::class)->canAccommodateRoomRequest(
            $type,
            $reservation->check_in_date,
            $reservation->check_out_date,
            4,
            2,
        ));
    }

    private function roomType(string $name, string $sharing = 'private'): RoomType
    {
        return RoomType::create(['name' => $name.uniqid(), 'base_rate' => 500, 'pricing_type' => 'flat_rate', 'room_sharing_type' => $sharing, 'is_active' => true]);
    }

    private function room(RoomType $type, string $number, int $capacity = 2): Room
    {
        $floor = Floor::firstOrCreate(['name' => 'Offer floor'], ['level' => 1, 'is_active' => true]);
        return Room::create(['room_number' => $number.uniqid(), 'room_type_id' => $type->id, 'floor_id' => $floor->id, 'capacity' => $capacity, 'status' => 'available', 'is_active' => true]);
    }

    private function reservation(RoomType $type, int $guests = 1): Reservation
    {
        return Reservation::create(['guest_first_name' => 'Alt', 'guest_last_name' => 'Guest', 'guest_email' => uniqid().'@example.com', 'guest_phone' => '09171234567', 'preferred_room_type_id' => $type->id, 'check_in_date' => now()->addDays(3)->toDateString(), 'check_out_date' => now()->addDays(5)->toDateString(), 'number_of_occupants' => $guests, 'status' => 'pending']);
    }
}
