<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\TourWaypoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuestControllerTest extends TestCase
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

    private function createRoomType(array $overrides = []): RoomType
    {
        return RoomType::create(array_merge([
            'name' => 'Standard Room ' . uniqid(),
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ], $overrides));
    }

    private function createRoom(RoomType $roomType, string $status = 'available'): Room
    {
        $floor = Floor::firstOrCreate(
            ['name' => 'Ground Floor'],
            ['level' => 1, 'is_active' => true]
        );

        return Room::create([
            'room_number' => 'R' . uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 4,
            'status' => $status,
            'is_active' => true,
        ]);
    }

    // ── Home Page ────────────────────────────────────────────

    public function test_home_page_returns_200(): void
    {
        $this->createRoomType();

        $response = $this->get(route('guest.home'));
        $response->assertStatus(200);
    }

    public function test_home_page_displays_active_room_types(): void
    {
        $active = $this->createRoomType(['name' => 'Active Suite', 'is_active' => true]);
        $inactive = $this->createRoomType(['name' => 'Hidden Room', 'is_active' => false]);

        $response = $this->get(route('guest.home'));

        $response->assertStatus(200);
        $response->assertSee('Active Suite');
        $response->assertDontSee('Hidden Room');
    }

    // ── Rooms Catalog ────────────────────────────────────────

    public function test_rooms_page_returns_200(): void
    {
        $response = $this->get(route('guest.rooms'));
        $response->assertStatus(200);
    }

    public function test_rooms_page_shows_only_active_room_types(): void
    {
        $this->createRoomType(['name' => 'Visible Dorm', 'is_active' => true]);
        $this->createRoomType(['name' => 'Inactive Dorm', 'is_active' => false]);

        $response = $this->get(route('guest.rooms'));

        $response->assertSee('Visible Dorm');
        $response->assertDontSee('Inactive Dorm');
    }

    // ── Room Detail ──────────────────────────────────────────

    public function test_room_detail_page_returns_200(): void
    {
        $roomType = $this->createRoomType();
        $this->createRoom($roomType);

        $response = $this->get(route('guest.room-detail', $roomType));
        $response->assertStatus(200);
    }

    public function test_room_detail_shows_room_info(): void
    {
        $roomType = $this->createRoomType(['name' => 'Deluxe Suite']);
        $room = $this->createRoom($roomType);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertSee('Deluxe Suite');
    }

    public function test_room_detail_links_to_matching_room_tour_scene_when_available(): void
    {
        $roomType = $this->createRoomType(['name' => 'Deluxe Suite']);
        $this->createRoom($roomType);

        TourWaypoint::create([
            'name' => 'Deluxe Suite Door',
            'slug' => 'deluxe-suite-door',
            'type' => 'room-door',
            'panorama_image' => 'virtual-tour/panoramas/deluxe-door.jpg',
            'position_order' => 2,
            'linked_room_type_id' => $roomType->id,
            'is_active' => true,
        ]);

        TourWaypoint::create([
            'name' => 'Deluxe Suite Interior',
            'slug' => 'deluxe-suite-interior',
            'type' => 'room-interior',
            'panorama_image' => 'virtual-tour/panoramas/deluxe-interior.jpg',
            'position_order' => 3,
            'linked_room_type_id' => $roomType->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertSee(route('guest.tour.viewer', ['slug' => 'deluxe-suite-interior']), false);
        $response->assertSee('View This Room in 360', false);
    }

    public function test_room_detail_falls_back_to_default_tour_when_no_matching_scene_exists(): void
    {
        $roomType = $this->createRoomType(['name' => 'Economy Room']);
        $this->createRoom($roomType);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertSee(route('guest.tour.viewer'), false);
        $response->assertSee('Start Virtual Tour', false);
    }

    // ── Virtual Tours ────────────────────────────────────────

    public function test_virtual_tours_page_returns_200(): void
    {
        $response = $this->get(route('guest.virtual-tours'));
        $response->assertStatus(200);
    }

    // ── Reserve Form ─────────────────────────────────────────

    public function test_reserve_form_returns_200(): void
    {
        $response = $this->get(route('guest.reserve'));
        $response->assertStatus(200);
    }

    // ── Reserve Submit ───────────────────────────────────────

    public function test_reserve_submit_creates_reservation(): void
    {
        $roomType = $this->createRoomType();

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 2,
        ]);

        $response->assertRedirect(route('guest.track'));
        $this->assertDatabaseHas('reservations', [
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'status' => 'pending',
        ]);
    }

    public function test_reserve_submit_validates_required_fields(): void
    {
        $response = $this->post(route('guest.reserve.submit'), []);

        $response->assertSessionHasErrors([
            'guest_last_name',
            'guest_first_name',
            'guest_gender',
            'guest_email',
            'preferred_room_type_id',
            'check_in_date',
            'check_out_date',
            'number_of_occupants',
        ]);
    }

    public function test_reserve_submit_rejects_past_check_in_date(): void
    {
        $roomType = $this->createRoomType();

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'number_of_occupants' => 1,
        ]);

        $response->assertSessionHasErrors('check_in_date');
    }

    public function test_reserve_submit_rejects_checkout_before_checkin(): void
    {
        $roomType = $this->createRoomType();

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'number_of_occupants' => 1,
        ]);

        $response->assertSessionHasErrors('check_out_date');
    }

    // ── Track Reservation ────────────────────────────────────

    public function test_track_page_returns_200(): void
    {
        $response = $this->get(route('guest.track'));
        $response->assertStatus(200);
    }

    public function test_track_finds_reservation_by_reference(): void
    {
        $roomType = $this->createRoomType();
        $reservation = Reservation::create([
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'pending',
        ]);

        $response = $this->get(route('guest.track', ['reference' => $reservation->reference_number]));

        $response->assertStatus(200);
        $response->assertSee($reservation->reference_number);
    }

    public function test_track_shows_nothing_for_invalid_reference(): void
    {
        $response = $this->get(route('guest.track', ['reference' => 'INVALID-REF']));

        $response->assertStatus(200);
    }

    public function test_track_expires_old_checked_out_reservations(): void
    {
        $roomType = $this->createRoomType();
        $reservation = Reservation::create([
            'guest_first_name' => 'Old',
            'guest_last_name' => 'Guest',
            'guest_email' => 'old@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->subDays(40),
            'check_out_date' => now()->subDays(35),
            'number_of_occupants' => 1,
            'status' => 'checked_out',
        ]);

        // Backdate the updated_at to 31 days ago
        Reservation::where('id', $reservation->id)->update(['updated_at' => now()->subDays(31)]);

        $response = $this->get(route('guest.track', ['reference' => $reservation->reference_number]));

        $response->assertStatus(200);
        // The reservation should be treated as expired and not shown
        $response->assertDontSee('old@example.com');
    }
}
