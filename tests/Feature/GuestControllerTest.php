<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\GuestAccount;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\ReservationFeedback;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\TourWaypoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    private function createReservationForRoomType(RoomType $roomType, array $overrides = []): Reservation
    {
        return Reservation::create(array_merge([
            'guest_first_name' => 'Booked',
            'guest_last_name' => 'Guest',
            'guest_email' => 'booked-'.uniqid().'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'checked_in',
        ], $overrides));
    }

    private function createPublicTestimonial(RoomType $roomType, string $comments, bool $displayRoomType = true): ReservationFeedback
    {
        $account = GuestAccount::create([
            'first_name' => 'Testimonial',
            'last_name' => 'Guest',
            'email' => 'testimonial-'.uniqid().'@example.com',
            'phone' => '09171234567',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $reservation = Reservation::create([
            'guest_account_id' => $account->id,
            'guest_first_name' => $account->first_name,
            'guest_last_name' => $account->last_name,
            'guest_email' => $account->email,
            'guest_phone' => $account->phone,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->subDays(3),
            'check_out_date' => now()->subDay(),
            'number_of_occupants' => 1,
            'status' => 'checked_out',
        ]);

        return ReservationFeedback::create([
            'reservation_id' => $reservation->id,
            'guest_account_id' => $account->id,
            'overall_rating' => 5,
            'comments' => $comments,
            'status' => 'reviewed',
            'visibility_status' => 'public',
            'public_display_consent' => true,
            'public_display_room_type' => $displayRoomType,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }

    private function occupyRoomForDates(RoomType $roomType, string $checkInDate, string $checkOutDate): Room
    {
        $user = $this->createStaffUser();
        $room = $this->createRoom($roomType, 'occupied');
        $reservation = $this->createReservationForRoomType($roomType, [
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'number_of_occupants' => 1,
        ]);

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'assigned_by' => $user->id,
        ]);

        return $room;
    }

    private function createStaffUser(): User
    {
        return User::create([
            'name' => 'Staff '.uniqid(),
            'email' => 'staff-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    // ── Home Page ────────────────────────────────────────────

    public function test_home_page_returns_200(): void
    {
        $this->createRoomType();

        $this->get(route('guest.home'))
            ->assertOk()
            ->assertDontSee('panorama-vendor', false)
            ->assertDontSee('tour-pill-dot', false)
            ->assertDontSee('@keyframes tour-ping', false);
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

    public function test_home_page_uses_generated_responsive_room_card_images(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(1200, 720);
        ob_start();
        imagejpeg($image, null, 90);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put('room-types/home-card.jpg', $contents);
        $this->createRoomType([
            'name' => 'Responsive Card Room',
            'images' => ['room-types/home-card.jpg'],
        ]);

        Storage::disk('public')->assertExists('room-types/home-card.jpg');
        Storage::disk('public')->assertExists('room-types/home-card.card-480.webp');
        Storage::disk('public')->assertExists('room-types/home-card.card-960.webp');

        $this->get(route('guest.home'))
            ->assertOk()
            ->assertSee('/storage/room-types/home-card.card-480.webp 480w', false)
            ->assertSee('/storage/room-types/home-card.card-960.webp 960w', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false);
    }

    public function test_room_card_variant_command_only_generates_missing_variants(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(640, 360);
        ob_start();
        imagejpeg($image, null, 90);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put('room-types/backfill.jpg', $contents);
        $roomType = $this->createRoomType(['images' => []]);
        $roomType->updateQuietly(['images' => ['room-types/backfill.jpg']]);

        Storage::disk('public')->assertMissing('room-types/backfill.card-480.webp');
        $this->artisan('media:generate-room-card-variants')->assertSuccessful();
        Storage::disk('public')->assertExists('room-types/backfill.card-480.webp');
        Storage::disk('public')->assertExists('room-types/backfill.card-960.webp');
    }

    public function test_home_page_uses_guest_site_settings(): void
    {
        $this->createRoomType();
        Setting::set('guest_site_title', 'Custom Homestay');
        Setting::set('guest_hero_headline', 'Stay at the Campus Lodge');
        Setting::set('guest_hero_message', 'A configurable public welcome message.');
        Setting::set('guest_announcement_enabled', '1');
        Setting::set('guest_announcement_text', 'Public notice for guests.');
        Setting::set('guest_show_booking_policy', '1');
        Setting::set('guest_booking_policy', 'Bring a valid ID during check-in.');
        Setting::set('guest_reservation_processing_time', 'Most requests are reviewed within one working day.');
        Setting::set('guest_show_faq', '1');
        Setting::set('guest_faq_items', json_encode([
            ['question' => 'Do you accept walk-ins?', 'answer' => 'Please submit a request first.'],
        ]));

        $response = $this->get(route('guest.home'));

        $response->assertStatus(200);
        $response->assertSee('Stay at the Campus Lodge');
        $response->assertSee('A configurable public welcome message.');
        $response->assertSee('Public notice for guests.');
        $response->assertSee('Bring a valid ID during check-in.');
        $response->assertSee('Most requests are reviewed within one working day.');
        $response->assertSee('Do you accept walk-ins?');
    }

    public function test_home_page_displays_only_reviewed_consenting_public_testimonials(): void
    {
        $roomType = $this->createRoomType(['name' => 'Testimonial Suite']);
        $account = GuestAccount::create([
            'first_name' => 'Alice',
            'last_name' => 'Valencia',
            'email' => 'alice-testimonial@example.com',
            'phone' => '09171234567',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $reservation = Reservation::create([
            'guest_account_id' => $account->id,
            'guest_first_name' => 'Alice',
            'guest_last_name' => 'Valencia',
            'guest_email' => $account->email,
            'guest_phone' => $account->phone,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->subDays(3),
            'check_out_date' => now()->subDay(),
            'number_of_occupants' => 1,
            'status' => 'checked_out',
        ]);

        ReservationFeedback::create([
            'reservation_id' => $reservation->id,
            'guest_account_id' => $account->id,
            'overall_rating' => 5,
            'comments' => 'Wonderful approved testimonial.',
            'status' => 'reviewed',
            'visibility_status' => 'public',
            'public_display_consent' => true,
            'public_display_room_type' => true,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $this->createRoom($roomType)->id,
            'assigned_by' => $this->createStaffUser()->id,
            'status' => 'checked_out',
            'checked_in_at' => now()->subDays(3),
            'checked_out_at' => now()->subDay(),
        ]);

        $otherReservation = $this->createReservationForRoomType($roomType, [
            'guest_account_id' => $account->id,
            'guest_email' => $account->email,
            'status' => 'checked_out',
        ]);
        $internalFeedback = ReservationFeedback::create([
            'reservation_id' => $otherReservation->id,
            'guest_account_id' => $account->id,
            'overall_rating' => 4,
            'comments' => 'Internal feedback must stay private.',
            'status' => 'reviewed',
            'visibility_status' => 'public',
            'public_display_consent' => false,
            'public_display_room_type' => true,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->assertSame('internal', $internalFeedback->visibility_status);
        $this->assertFalse($internalFeedback->public_display_room_type);

        $response = $this->get(route('guest.home'));

        $response->assertOk()
            ->assertSee('What Our Guests Say')
            ->assertSee('Wonderful approved testimonial.')
            ->assertSee('Verified guest')
            ->assertSee('Stayed in: Testimonial Suite')
            ->assertDontSee('Internal feedback must stay private.')
            ->assertDontSee('alice-testimonial@example.com')
            ->assertDontSee('Alice Valencia')
            ->assertDontSee('Alice V.')
            ->assertDontSee('Approved testimonial')
            ->assertDontSee('Verified guest &bull; Testimonial Suite', false);
        $response->assertViewHas('testimonials', fn ($testimonials): bool => $testimonials->count() === 1);
    }

    public function test_home_page_hides_room_label_for_testimonials_with_multiple_assigned_room_types(): void
    {
        $primaryType = $this->createRoomType(['name' => 'Primary Testimonial Room']);
        $secondType = $this->createRoomType(['name' => 'Second Testimonial Room']);
        $feedback = $this->createPublicTestimonial($primaryType, 'Multi-room testimonial.');
        $staff = $this->createStaffUser();

        foreach ([$primaryType, $secondType] as $roomType) {
            RoomAssignment::create([
                'reservation_id' => $feedback->reservation_id,
                'room_id' => $this->createRoom($roomType)->id,
                'assigned_by' => $staff->id,
                'status' => 'checked_out',
            ]);
        }

        $this->get(route('guest.home'))
            ->assertOk()
            ->assertSee('Multi-room testimonial.')
            ->assertDontSee('Stayed in: Primary Testimonial Room')
            ->assertDontSee('Stayed in: Second Testimonial Room');
    }

    public function test_home_page_hides_room_label_without_an_assignment(): void
    {
        $roomType = $this->createRoomType(['name' => 'Requested But Not Assigned']);
        $this->createPublicTestimonial($roomType, 'No-assignment testimonial.');

        $this->get(route('guest.home'))
            ->assertOk()
            ->assertSee('No-assignment testimonial.')
            ->assertDontSee('Stayed in: Requested But Not Assigned');
    }

    public function test_home_page_hides_room_label_without_guest_consent(): void
    {
        $roomType = $this->createRoomType(['name' => 'No Consent Testimonial Room']);
        $feedback = $this->createPublicTestimonial($roomType, 'No-consent testimonial.', false);

        RoomAssignment::create([
            'reservation_id' => $feedback->reservation_id,
            'room_id' => $this->createRoom($roomType)->id,
            'assigned_by' => $this->createStaffUser()->id,
            'status' => 'checked_out',
        ]);

        $this->get(route('guest.home'))
            ->assertOk()
            ->assertSee('No-consent testimonial.')
            ->assertDontSee('Stayed in: No Consent Testimonial Room');
    }

    public function test_home_page_keeps_gradient_fallback_without_enabled_hero_background(): void
    {
        $this->createRoomType();
        Setting::set('guest_hero_background_image', 'site-settings/hero/lobby.jpg');
        Setting::set('guest_hero_background_enabled', '0');

        $response = $this->get(route('guest.home'));

        $response->assertStatus(200);
        $response->assertDontSee('/storage/site-settings/hero/lobby.jpg', false);
    }

    public function test_home_page_renders_enabled_hero_background_image(): void
    {
        $this->createRoomType();
        Setting::set('guest_hero_background_image', 'site-settings/hero/lobby.jpg');
        Setting::set('guest_hero_background_enabled', '1');
        Setting::set('guest_hero_background_opacity', '82');

        $response = $this->get(route('guest.home'));

        $response->assertStatus(200);
        $response->assertSee('/storage/site-settings/hero/lobby.jpg', false);
        $response->assertSee('absolute inset-0 h-full w-full object-cover', false);
        $response->assertSee('opacity: 0.59', false);
        $response->assertSee('rgba(0, 35, 14, 0.82)', false);
        $response->assertSee('backdrop-filter: blur(18px)', false);
    }

    // ── Rooms Catalog ────────────────────────────────────────

    public function test_rooms_page_returns_200(): void
    {
        $response = $this->get(route('guest.rooms'));
        $response->assertStatus(200);
    }

    public function test_rooms_page_collapses_advanced_search_by_default(): void
    {
        $response = $this->get(route('guest.rooms'));

        $response->assertStatus(200);
        $response->assertSee('Advanced Search');
        $response->assertDontSee('<details class="group rounded-lg border border-[#00491E]/15 bg-[#00491E]/5"  open >', false);
    }

    public function test_rooms_page_opens_advanced_search_when_advanced_filter_is_active(): void
    {
        $response = $this->get(route('guest.rooms', [
            'sort' => 'price_low',
        ]));

        $response->assertStatus(200);
        $response->assertSee('<details class="group rounded-lg border border-[#00491E]/15 bg-[#00491E]/5"  open >', false);
        $response->assertSee('Lowest price first');
    }

    public function test_rooms_page_shows_only_active_room_types(): void
    {
        $this->createRoomType(['name' => 'Visible Dorm', 'is_active' => true]);
        $this->createRoomType(['name' => 'Inactive Dorm', 'is_active' => false]);

        $response = $this->get(route('guest.rooms'));

        $response->assertSee('Visible Dorm');
        $response->assertDontSee('Inactive Dorm');
    }

    public function test_rooms_page_filters_by_all_selected_amenities(): void
    {
        $wifi = Amenity::create(['name' => 'Wi-Fi', 'is_active' => true]);
        $privateCr = Amenity::create(['name' => 'Private CR', 'is_active' => true]);
        $ceilingFan = Amenity::create(['name' => 'Ceiling Fan', 'is_active' => true]);

        $deluxe = $this->createRoomType(['name' => 'Amenity Deluxe']);
        $basic = $this->createRoomType(['name' => 'Amenity Basic']);
        $fanRoom = $this->createRoomType(['name' => 'Amenity Fan Room']);

        $deluxe->amenities()->attach([$wifi->id, $privateCr->id]);
        $basic->amenities()->attach([$wifi->id]);
        $fanRoom->amenities()->attach([$wifi->id, $ceilingFan->id]);
        $this->createRoom($deluxe);
        $this->createRoom($basic);
        $this->createRoom($fanRoom);

        $response = $this->get(route('guest.rooms', [
            'amenities' => [$wifi->id, $privateCr->id],
        ]));

        $response->assertStatus(200);
        $response->assertSee('Amenity Deluxe');
        $response->assertDontSee('Amenity Basic');
        $response->assertDontSee('Amenity Fan Room');
        $response->assertSee('Active filters:');
        $response->assertSee('Wi-Fi');
        $response->assertSee('Private CR');
    }

    public function test_rooms_page_filters_by_setup_pricing_type_and_budget(): void
    {
        $matching = $this->createRoomType([
            'name' => 'Filtered Private Room',
            'base_rate' => 1200,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
        ]);
        $shared = $this->createRoomType([
            'name' => 'Filtered Shared Dorm',
            'base_rate' => 1200,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'public',
        ]);
        $perPerson = $this->createRoomType([
            'name' => 'Filtered Per Person',
            'base_rate' => 1200,
            'pricing_type' => 'per_person',
            'room_sharing_type' => 'private',
        ]);
        $expensive = $this->createRoomType([
            'name' => 'Filtered Expensive Room',
            'base_rate' => 1700,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
        ]);

        foreach ([$matching, $shared, $perPerson, $expensive] as $roomType) {
            $this->createRoom($roomType);
        }

        $response = $this->get(route('guest.rooms', [
            'room_sharing_type' => 'private',
            'pricing_type' => 'flat_rate',
            'price_min' => 1000,
            'price_max' => 1300,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Filtered Private Room');
        $response->assertDontSee('Filtered Shared Dorm');
        $response->assertDontSee('Filtered Per Person');
        $response->assertDontSee('Filtered Expensive Room');
        $response->assertSee('Private rooms');
        $response->assertSee('Per room/night');
        $response->assertSee('PHP 1,000 - PHP 1,300');
    }

    public function test_rooms_page_sorts_by_price_capacity_and_name(): void
    {
        $alpha = $this->createRoomType(['name' => 'Alpha Sort Room', 'base_rate' => 1500]);
        $bravo = $this->createRoomType(['name' => 'Bravo Sort Room', 'base_rate' => 800]);
        $charlie = $this->createRoomType(['name' => 'Charlie Sort Room', 'base_rate' => 1200]);
        $this->createRoom($alpha)->update(['capacity' => 2]);
        $this->createRoom($bravo)->update(['capacity' => 6]);
        $this->createRoom($charlie)->update(['capacity' => 4]);

        $lowest = $this->get(route('guest.rooms', ['sort' => 'price_low']));
        $lowest->assertViewHas('roomTypes', fn ($roomTypes): bool => $roomTypes->pluck('name')->values()->all() === [
            'Bravo Sort Room',
            'Charlie Sort Room',
            'Alpha Sort Room',
        ]);

        $highest = $this->get(route('guest.rooms', ['sort' => 'price_high']));
        $highest->assertViewHas('roomTypes', fn ($roomTypes): bool => $roomTypes->pluck('name')->values()->all() === [
            'Alpha Sort Room',
            'Charlie Sort Room',
            'Bravo Sort Room',
        ]);

        $capacity = $this->get(route('guest.rooms', ['sort' => 'capacity']));
        $capacity->assertViewHas('roomTypes', fn ($roomTypes): bool => $roomTypes->pluck('name')->values()->all() === [
            'Bravo Sort Room',
            'Charlie Sort Room',
            'Alpha Sort Room',
        ]);

        $name = $this->get(route('guest.rooms', ['sort' => 'name']));
        $name->assertViewHas('roomTypes', fn ($roomTypes): bool => $roomTypes->pluck('name')->values()->all() === [
            'Alpha Sort Room',
            'Bravo Sort Room',
            'Charlie Sort Room',
        ]);
    }

    public function test_rooms_page_keeps_shared_room_type_available_when_remaining_beds_cover_requested_guests(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType([
            'name' => 'Dormitory',
            'room_sharing_type' => 'public',
        ]);

        $room = $this->createRoom($roomType);
        $room->update(['capacity' => 8]);

        $reservation = $this->createReservationForRoomType($roomType, [
            'check_in_date' => '2026-04-29',
            'check_out_date' => '2026-04-30',
            'number_of_occupants' => 1,
        ]);

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'assigned_by' => $user->id,
        ]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 2,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('roomTypes', function ($roomTypes) use ($roomType) {
            $dormitory = $roomTypes->firstWhere('id', $roomType->id);

            return $dormitory
                && $dormitory->available_beds_count === 7
                && $dormitory->total_beds_count === 8
                && $dormitory->can_accommodate_requested_guests === true;
        });
    }

    public function test_current_shared_room_availability_counts_open_beds_in_reserved_dorms(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType([
            'name' => 'Dormitory',
            'room_sharing_type' => 'public',
        ]);

        $reservedDorm = $this->createRoom($roomType);
        $reservedDorm->update([
            'capacity' => 20,
            'status' => 'reserved',
        ]);

        $openDorm = $this->createRoom($roomType);
        $openDorm->update([
            'capacity' => 20,
            'status' => 'available',
        ]);

        $reservation = $this->createReservationForRoomType($roomType, [
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'number_of_occupants' => 5,
        ]);

        foreach (range(1, 4) as $index) {
            RoomAssignment::create([
                'reservation_id' => $reservation->id,
                'room_id' => $reservedDorm->id,
                'status' => 'checked_in',
                'checked_in_at' => now(),
                'assigned_by' => $user->id,
            ]);
        }

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $openDorm->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'assigned_by' => $user->id,
        ]);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertViewHas('roomType', function (RoomType $dormitory): bool {
            return $dormitory->available_beds_count === 35
                && $dormitory->total_beds_count === 40
                && $dormitory->available_rooms_count === 2;
        });
    }

    public function test_rooms_page_marks_shared_room_type_unavailable_when_requested_guests_exceed_remaining_beds(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $roomType = $this->createRoomType([
            'name' => 'Dormitory',
            'room_sharing_type' => 'public',
        ]);

        $room = $this->createRoom($roomType);
        $room->update(['capacity' => 8]);

        $reservation = $this->createReservationForRoomType($roomType, [
            'check_in_date' => '2026-04-29',
            'check_out_date' => '2026-04-30',
            'number_of_occupants' => 1,
        ]);

        RoomAssignment::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'assigned_by' => $user->id,
        ]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 8,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('roomTypes', function ($roomTypes) use ($roomType) {
            $dormitory = $roomTypes->firstWhere('id', $roomType->id);

            return $dormitory
                && $dormitory->available_beds_count === 7
                && $dormitory->can_accommodate_requested_guests === false;
        });
    }

    public function test_rooms_page_keeps_private_room_type_unavailable_when_requested_guests_exceed_room_capacity(): void
    {
        $roomType = $this->createRoomType([
            'name' => 'Standard Room',
            'room_sharing_type' => 'private',
        ]);

        $room = $this->createRoom($roomType);
        $room->update(['capacity' => 2]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 3,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('roomTypes', function ($roomTypes) use ($roomType) {
            $private = $roomTypes->firstWhere('id', $roomType->id);

            return $private
                && $private->available_rooms_count === 1
                && $private->can_accommodate_requested_guests === false;
        });
    }

    public function test_rooms_page_splits_mixed_capacity_room_types_and_filters_for_party_size(): void
    {
        $roomType = $this->createRoomType([
            'name' => 'Family Room',
            'room_sharing_type' => 'private',
        ]);

        $this->createRoom($roomType)->update(['capacity' => 3]);
        $this->createRoom($roomType)->update(['capacity' => 4]);

        $fourGuestResponse = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 4,
        ]));

        $fourGuestResponse->assertStatus(200);
        $fourGuestResponse->assertViewHas('roomTypes', function ($roomTypes) use ($roomType): bool {
            $variants = $roomTypes->where('id', $roomType->id);

            return $variants->count() === 1
                && $variants->first()->variant_capacity === 4
                && $variants->first()->available_rooms_count === 1;
        });

        $threeGuestResponse = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 3,
        ]));

        $threeGuestResponse->assertViewHas('roomTypes', function ($roomTypes) use ($roomType): bool {
            return $roomTypes->where('id', $roomType->id)
                ->pluck('variant_capacity')
                ->values()
                ->all() === [3, 4];
        });
    }

    // ── Room Detail ──────────────────────────────────────────

    public function test_rooms_page_lists_available_room_types_before_unavailable_room_types(): void
    {
        $unavailable = $this->createRoomType([
            'name' => 'Alpha Unavailable Room',
            'room_sharing_type' => 'private',
        ]);
        $this->createRoom($unavailable)->update(['capacity' => 2]);

        $available = $this->createRoomType([
            'name' => 'Zulu Available Room',
            'room_sharing_type' => 'private',
        ]);
        $this->createRoom($available)->update(['capacity' => 5]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 3,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('roomTypes', function ($roomTypes) use ($available, $unavailable): bool {
            return $roomTypes->pluck('id')->values()->all() === [$available->id, $unavailable->id];
        });
    }

    public function test_rooms_page_hides_unavailable_room_types_when_toggle_is_off(): void
    {
        $available = $this->createRoomType(['name' => 'Available Toggle Room']);
        $this->createRoom($available)->update(['capacity' => 5]);

        $unavailable = $this->createRoomType(['name' => 'Unavailable Toggle Room']);
        $this->createRoom($unavailable)->update(['capacity' => 2]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 3,
            'show_unavailable' => 0,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Available Toggle Room');
        $response->assertDontSee('Unavailable Toggle Room');
        $response->assertSee('Showing available rooms only');
    }

    public function test_rooms_page_shows_unavailable_room_types_by_default(): void
    {
        $available = $this->createRoomType(['name' => 'Default Visible Available Room']);
        $this->createRoom($available)->update(['capacity' => 5]);

        $unavailable = $this->createRoomType(['name' => 'Default Visible Unavailable Room']);
        $this->createRoom($unavailable)->update(['capacity' => 2]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 3,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Default Visible Available Room');
        $response->assertSee('Default Visible Unavailable Room');
        $response->assertViewHas('showUnavailable', true);
    }

    public function test_rooms_page_available_only_empty_state_links_back_to_unavailable_results(): void
    {
        $unavailable = $this->createRoomType(['name' => 'Only Unavailable Room']);
        $this->createRoom($unavailable)->update(['capacity' => 2]);

        $response = $this->get(route('guest.rooms', [
            'check_in' => '2026-04-29',
            'check_out' => '2026-04-30',
            'guests' => 3,
            'show_unavailable' => 0,
        ]));

        $response->assertStatus(200);
        $response->assertDontSee('Only Unavailable Room');
        $response->assertSee('No available rooms match this search.');
        $response->assertSee('show_unavailable=1', false);
    }

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
        $response->assertSee(route('guest.tour.viewer', ['slug' => 'deluxe-suite-interior'], false), false);
        $response->assertSee('View This Room in 360', false);
    }

    public function test_room_detail_falls_back_to_default_tour_when_no_matching_scene_exists(): void
    {
        $roomType = $this->createRoomType(['name' => 'Economy Room']);
        $this->createRoom($roomType);

        $response = $this->get(route('guest.room-detail', $roomType));

        $response->assertStatus(200);
        $response->assertSee(route('guest.tour.viewer', [], false), false);
        $response->assertSee('Start Virtual Tour', false);
    }

    // ── Availability Search Continuity ───────────────────────

    public function test_rooms_page_preserves_availability_search_params_in_room_detail_links(): void
    {
        $roomType = $this->createRoomType(['name' => 'Searchable Room']);
        $this->createRoom($roomType);

        $checkIn = now()->addDays(5)->toDateString();
        $checkOut = now()->addDays(7)->toDateString();

        $response = $this->get(route('guest.rooms', [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ]));

        $response->assertStatus(200);
        $response->assertSee(e(route('guest.room-detail', [
            'roomType' => $roomType,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ], false)), false);
    }

    public function test_room_detail_preserves_availability_search_params_in_reservation_link(): void
    {
        $roomType = $this->createRoomType(['name' => 'Deluxe Room']);
        $this->createRoom($roomType);

        $checkIn = now()->addDays(5)->toDateString();
        $checkOut = now()->addDays(7)->toDateString();

        $response = $this->get(route('guest.room-detail', [
            'roomType' => $roomType,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('roomType', fn (RoomType $roomType): bool => $roomType->is_date_filtered === true);
        $response->assertSee(e(route('guest.reserve', [
            'room_type' => $roomType->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ], false)), false);
    }

    // ── Virtual Tours ────────────────────────────────────────

    public function test_virtual_tours_page_returns_200(): void
    {
        $response = $this->get(route('guest.virtual-tours'));
        $response->assertRedirect(route('guest.tour.viewer'));
    }

    // ── Reserve Form ─────────────────────────────────────────

    public function test_reserve_form_returns_200(): void
    {
        $response = $this->get(route('guest.reserve'));
        $response->assertStatus(200);
    }

    public function test_reserve_form_displays_configured_processing_time_guidance(): void
    {
        Setting::set('guest_reservation_processing_time', 'Most requests are reviewed within one working day.');

        $response = $this->get(route('guest.reserve'));

        $response->assertStatus(200);
        $response->assertSee('Most requests are reviewed within one working day.');
        $response->assertSee('Confirmation is sent after staff approval.');
    }

    // ── Reservation Query Defaults ───────────────────────────

    public function test_reserve_form_defaults_dates_to_today_and_tomorrow_without_query_params(): void
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $response = $this->get(route('guest.reserve'));

        $response->assertStatus(200);
        $response->assertSee('name="check_in_date" id="check_in_date" value="'.$today.'"', false);
        $response->assertSee('min="'.$today.'"', false);
        $response->assertSee('name="check_out_date" id="check_out_date" value="'.$tomorrow.'"', false);
        $response->assertSee('min="'.$tomorrow.'"', false);
    }

    public function test_reserve_form_prefills_room_dates_and_occupants_from_query_params(): void
    {
        $roomType = $this->createRoomType(['name' => 'Prefilled Room']);
        $this->createRoom($roomType);

        $checkIn = now()->addDays(5)->toDateString();
        $checkOut = now()->addDays(7)->toDateString();

        $response = $this->get(route('guest.reserve', [
            'room_type' => $roomType->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ]));

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/<option\s+value="'.preg_quote((string) $roomType->id, '/').'"[^>]*\bselected\b/',
            $response->getContent()
        );
        $response->assertSee('name="check_in_date" id="check_in_date" value="'.$checkIn.'"', false);
        $response->assertSee('name="check_out_date" id="check_out_date" value="'.$checkOut.'"', false);
        $response->assertSee('name="number_of_occupants" id="number_of_occupants" value="2"', false);
    }

    // ── Reserve Submit ───────────────────────────────────────

    public function test_rooms_page_defaults_filter_dates_to_today_and_tomorrow_without_query_params(): void
    {
        $this->createRoom($this->createRoomType(['name' => 'Default Date Room']));

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $response = $this->get(route('guest.rooms'));

        $response->assertStatus(200);
        $response->assertSee('id="check_in_filter" name="check_in" value="'.$today.'" min="'.$today.'"', false);
        $response->assertSee('id="check_out_filter" name="check_out" value="'.$tomorrow.'" min="'.$tomorrow.'"', false);
    }

    public function test_reserve_submit_creates_reservation(): void
    {
        $roomType = $this->createRoomType();
        $this->createRoom($roomType);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
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
        $reservation = Reservation::where('guest_email', 'john@example.com')->first();
        $this->assertDatabaseHas('reservation_room_requests', [
            'reservation_id' => $reservation->id,
            'room_type_id' => $roomType->id,
            'requested_room_count' => 1,
            'occupant_count' => 2,
        ]);
    }

    public function test_reserve_submit_creates_multiple_room_request_lines(): void
    {
        $executive = $this->createRoomType(['name' => 'Executive Multi']);
        $dormitory = $this->createRoomType([
            'name' => 'Dormitory Multi',
            'room_sharing_type' => 'public',
            'pricing_type' => 'per_person',
        ]);
        $this->createRoom($executive)->update(['capacity' => 4]);
        $this->createRoom($executive)->update(['capacity' => 4]);
        $this->createRoom($dormitory)->update(['capacity' => 8]);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Group',
            'guest_first_name' => 'Lead',
            'guest_gender' => 'Female',
            'guest_email' => 'group@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 22,
            'preferred_room_type_id' => $executive->id,
            'requested_room_count' => 2,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 5,
            'room_requests' => [
                [
                    'room_type_id' => $dormitory->id,
                    'requested_room_count' => 1,
                    'occupant_count' => 3,
                    'notes' => 'For facilitators',
                ],
            ],
        ]);

        $response->assertRedirect(route('guest.track'));

        $reservation = Reservation::where('guest_email', 'group@example.com')->first();
        $this->assertNotNull($reservation);
        $this->assertSame(8, (int) $reservation->number_of_occupants);
        $this->assertDatabaseHas('reservation_room_requests', [
            'reservation_id' => $reservation->id,
            'room_type_id' => $executive->id,
            'requested_room_count' => 2,
            'occupant_count' => 5,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('reservation_room_requests', [
            'reservation_id' => $reservation->id,
            'room_type_id' => $dormitory->id,
            'requested_room_count' => 1,
            'occupant_count' => 3,
            'sort_order' => 1,
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
            'guest_phone',
            'guest_age',
            'preferred_room_type_id',
            'check_in_date',
            'check_out_date',
            'number_of_occupants',
        ]);
    }

    public function test_reserve_submit_requires_acknowledgement_when_availability_looks_unavailable(): void
    {
        $roomType = $this->createRoomType();
        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();
        $this->occupyRoomForDates($roomType, $checkIn, $checkOut);

        $response = $this->from(route('guest.reserve'))->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'number_of_occupants' => 1,
        ]);

        $response->assertRedirect(route('guest.reserve'));
        $response->assertSessionHasErrors('availability_acknowledged');
        $this->assertDatabaseMissing('reservations', [
            'guest_email' => 'john@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_reserve_submit_allows_acknowledged_request_despite_low_availability(): void
    {
        $roomType = $this->createRoomType();
        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();
        $this->occupyRoomForDates($roomType, $checkIn, $checkOut);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john-ack@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'number_of_occupants' => 1,
            'availability_acknowledged' => '1',
        ]);

        $response->assertRedirect(route('guest.track'));

        $reservation = Reservation::where('guest_email', 'john-ack@example.com')->first();
        $this->assertNotNull($reservation);
        $this->assertStringContainsString('Availability warning acknowledged by guest', (string) $reservation->special_requests);
    }

    public function test_reserve_submit_rejects_past_check_in_date(): void
    {
        $roomType = $this->createRoomType();

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
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
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'number_of_occupants' => 1,
        ]);

        $response->assertSessionHasErrors('check_out_date');
    }

    // ── Track Reservation ────────────────────────────────────

    public function test_reserve_submit_rejects_primary_guest_younger_than_eighteen(): void
    {
        $roomType = $this->createRoomType();

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 17,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
        ]);

        $response->assertSessionHasErrors('guest_age');
    }

    public function test_reserve_submit_rejects_missing_required_mobile_number(): void
    {
        $roomType = $this->createRoomType();
        $this->createRoom($roomType);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'missing-mobile@example.com',
            'guest_age' => 18,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 1,
        ]);

        $response->assertSessionHasErrors('guest_phone');
    }

    public function test_reserve_submit_accepts_valid_philippine_mobile_formats(): void
    {
        foreach (['09171234567', '+639171234567', '639171234567'] as $mobile) {
            $roomType = $this->createRoomType();
            $this->createRoom($roomType);
            $email = 'valid-mobile-'.md5($mobile).'@example.com';

            $response = $this->post(route('guest.reserve.submit'), [
                'guest_last_name' => 'Doe',
                'guest_first_name' => 'John',
                'guest_gender' => 'Male',
                'guest_email' => $email,
                'guest_phone' => $mobile,
                'guest_age' => 18,
                'preferred_room_type_id' => $roomType->id,
                'check_in_date' => now()->addDay()->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'number_of_occupants' => 1,
            ]);

            $response->assertRedirect(route('guest.track'));
            $this->assertDatabaseHas('reservations', [
                'guest_email' => $email,
                'guest_phone' => $mobile,
            ]);
        }
    }

    public function test_reserve_submit_rejects_invalid_mobile_numbers(): void
    {
        foreach (['wewe', '123', '021234567'] as $mobile) {
            $roomType = $this->createRoomType();

            $response = $this->post(route('guest.reserve.submit'), [
                'guest_last_name' => 'Doe',
                'guest_first_name' => 'John',
                'guest_gender' => 'Male',
                'guest_email' => 'invalid-mobile-'.md5($mobile).'@example.com',
                'guest_phone' => $mobile,
                'guest_age' => 18,
                'preferred_room_type_id' => $roomType->id,
                'check_in_date' => now()->addDay()->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'number_of_occupants' => 1,
            ]);

            $response->assertSessionHasErrors('guest_phone');
        }
    }

    public function test_reserve_submit_rejects_private_room_occupants_above_capacity(): void
    {
        $roomType = $this->createRoomType(['name' => 'Capacity Limited Private Room']);
        $this->createRoom($roomType);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'private-capacity@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 5,
        ]);

        $response->assertSessionHasErrors('number_of_occupants');
    }

    public function test_reserve_submit_accepts_public_room_occupants_up_to_available_beds(): void
    {
        $roomType = $this->createRoomType([
            'name' => 'Dormitory Available Beds',
            'room_sharing_type' => 'public',
        ]);
        $this->createRoom($roomType);
        $this->createRoom($roomType);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'dorm-eight@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 8,
        ]);

        $response->assertRedirect(route('guest.track'));
        $this->assertDatabaseHas('reservations', [
            'guest_email' => 'dorm-eight@example.com',
            'number_of_occupants' => 8,
        ]);
    }

    public function test_reserve_submit_rejects_public_room_occupants_above_available_beds(): void
    {
        $roomType = $this->createRoomType([
            'name' => 'Dormitory Bed Limit',
            'room_sharing_type' => 'public',
        ]);
        $this->createRoom($roomType);
        $this->createRoom($roomType);

        $response = $this->post(route('guest.reserve.submit'), [
            'guest_last_name' => 'Doe',
            'guest_first_name' => 'John',
            'guest_gender' => 'Male',
            'guest_email' => 'dorm-nine@example.com',
            'guest_phone' => '09171234567',
            'guest_age' => 18,
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'number_of_occupants' => 9,
        ]);

        $response->assertSessionHasErrors('number_of_occupants');
    }

    public function test_track_page_returns_200(): void
    {
        $response = $this->get(route('guest.track'));
        $response->assertStatus(200);
    }

    public function test_guest_page_notifications_render_below_the_page_title(): void
    {
        $pages = [
            [route('guest.track'), 'Track Your Reservation Request'],
            [route('guest.rooms'), 'Room Catalog'],
            [route('guest.reserve'), 'Request a Stay'],
            [route('guest.support'), 'Support is available to verified guest accounts'],
        ];

        foreach ($pages as [$url, $title]) {
            $response = $this->withSession(['success' => 'Notification placement check.'])
                ->get($url);

            $response->assertOk()
                ->assertSeeInOrder([$title, 'Notification placement check.']);
        }
    }

    public function test_track_page_displays_configured_processing_time_guidance(): void
    {
        Setting::set('guest_reservation_processing_time', 'Most requests are reviewed within one working day.');

        $response = $this->get(route('guest.track'));

        $response->assertStatus(200);
        $response->assertSee('Most requests are reviewed within one working day.');
        $response->assertSee('Use this page to check for status updates.');
    }

    public function test_track_finds_reservation_by_reference_and_guest_email(): void
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

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertStatus(200);
        $response->assertSee($reservation->reference_number);
    }

    public function test_track_shows_nothing_for_invalid_reference(): void
    {
        $response = $this->get(route('guest.track', [
            'reference' => 'INVALID-REF',
            'guest_email' => 'guest@example.com',
        ]));

        $response->assertStatus(200);
    }

    public function test_track_requires_guest_email_for_manual_lookup(): void
    {
        $response = $this->get(route('guest.track', [
            'reference' => '2026-0001',
        ]));

        $response->assertSessionHasErrors('guest_email');
    }

    public function test_secure_track_link_allows_guest_lookup_without_manual_email_entry(): void
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

        $signedUrl = URL::temporarySignedRoute(
            'guest.track.secure',
            now()->addMinutes(30),
            ['reservation' => $reservation->id]
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertSee($reservation->reference_number);
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

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Tracking period has ended');
        $response->assertDontSee($reservation->reference_number.' status');
    }

    public function test_track_hides_payment_link_for_pending_requests(): void
    {
        $roomType = $this->createRoomType();
        $reservation = Reservation::create([
            'guest_first_name' => 'Pending',
            'guest_last_name' => 'Guest',
            'guest_email' => 'pending@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'pending',
        ]);

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertStatus(200);
        $response->assertDontSee('Pay Deposit Now');
    }

    public function test_track_shows_payment_link_for_approved_requests(): void
    {
        \App\Models\Setting::create(['key' => 'online_payments_enabled', 'value' => '1']);
        \App\Models\Setting::create(['key' => 'default_deposit_percentage', 'value' => '30']);

        $roomType = $this->createRoomType();
        $room = $this->createRoom($roomType);
        $reservation = Reservation::create([
            'guest_first_name' => 'Approved',
            'guest_last_name' => 'Guest',
            'guest_email' => 'approved@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 1,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $reservation->issueGuestPaymentLink(rotateToken: true);
        $reservation->save();

        RoomHold::create([
            'room_id' => $room->id,
            'reservation_id' => $reservation->id,
            'hold_from' => $reservation->check_in_date,
            'hold_to' => $reservation->check_out_date,
            'hold_type' => 'advance',
        ]);

        $response = $this->get(route('guest.track', [
            'reference' => $reservation->reference_number,
            'guest_email' => $reservation->guest_email,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Pay Deposit Now');
    }
}
