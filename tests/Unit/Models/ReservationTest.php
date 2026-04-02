<?php

namespace Tests\Unit\Models;

use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the reservation_sequences table exists for reference number generation
        if (!DB::getSchemaBuilder()->hasTable('reservation_sequences')) {
            DB::getSchemaBuilder()->create('reservation_sequences', function ($table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_sequence')->default(0);
            });
        }
    }

    private function createReservation(array $overrides = []): Reservation
    {
        $roomType = RoomType::create([
            'name' => 'Standard',
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        return Reservation::create(array_merge([
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'number_of_occupants' => 2,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_fillable_attributes(): void
    {
        $reservation = new Reservation;
        $fillable = $reservation->getFillable();

        $this->assertContains('reference_number', $fillable);
        $this->assertContains('guest_name', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('check_in_date', $fillable);
        $this->assertContains('check_out_date', $fillable);
    }

    public function test_casts(): void
    {
        $reservation = new Reservation;
        $casts = $reservation->getCasts();

        $this->assertEquals('date', $casts['check_in_date']);
        $this->assertEquals('date', $casts['check_out_date']);
        $this->assertEquals('integer', $casts['guest_age']);
        $this->assertEquals('array', $casts['checkin_hold_payload']);
        $this->assertEquals('decimal:2', $casts['addons_total']);
        $this->assertEquals('decimal:2', $casts['payments_total']);
        $this->assertEquals('decimal:2', $casts['balance_due']);
    }

    public function test_auto_generates_reference_number(): void
    {
        $reservation = $this->createReservation();

        $this->assertNotEmpty($reservation->reference_number);
        $this->assertStringStartsWith(now()->year . '-', $reservation->reference_number);
    }

    public function test_reference_number_increments(): void
    {
        $r1 = $this->createReservation(['guest_email' => 'a@example.com']);
        $r2 = $this->createReservation(['guest_email' => 'b@example.com']);

        $this->assertNotEquals($r1->reference_number, $r2->reference_number);
    }

    public function test_auto_populates_guest_name_on_save(): void
    {
        $reservation = $this->createReservation([
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Smith',
            'guest_middle_initial' => 'M',
        ]);

        $this->assertStringContainsString('Jane', $reservation->guest_name);
        $this->assertStringContainsString('Smith', $reservation->guest_name);
        $this->assertStringContainsString('M', $reservation->guest_name);
    }

    public function test_nights_attribute(): void
    {
        $reservation = $this->createReservation([
            'check_in_date' => '2026-04-01',
            'check_out_date' => '2026-04-04',
        ]);

        $this->assertEquals(3, $reservation->nights);
    }

    public function test_status_color_attribute(): void
    {
        $reservation = new Reservation;

        $reservation->status = 'pending';
        $this->assertEquals('warning', $reservation->status_color);

        $reservation->status = 'approved';
        $this->assertEquals('info', $reservation->status_color);

        $reservation->status = 'declined';
        $this->assertEquals('danger', $reservation->status_color);

        $reservation->status = 'cancelled';
        $this->assertEquals('gray', $reservation->status_color);

        $reservation->status = 'checked_in';
        $this->assertEquals('success', $reservation->status_color);

        $reservation->status = 'checked_out';
        $this->assertEquals('gray', $reservation->status_color);

        $reservation->status = 'unknown';
        $this->assertEquals('gray', $reservation->status_color);
    }

    public function test_preferred_room_type_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $reservation->preferredRoomType()
        );
    }

    public function test_reviewer_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $reservation->reviewer()
        );
    }

    public function test_guests_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $reservation->guests()
        );
    }

    public function test_room_assignments_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $reservation->roomAssignments()
        );
    }

    public function test_charges_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $reservation->charges()
        );
    }

    public function test_payments_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $reservation->payments()
        );
    }

    public function test_logs_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $reservation->logs()
        );
    }

    public function test_check_in_snapshots_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $reservation->checkInSnapshots()
        );
    }

    public function test_billing_guest_relationship(): void
    {
        $reservation = new Reservation;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $reservation->billingGuest()
        );
    }

    public function test_refresh_financial_summary_paid(): void
    {
        $reservation = $this->createReservation();

        $reservation->charges()->create([
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'scope_id' => $reservation->id,
            'description' => 'Room charges',
            'qty' => 1,
            'unit_price' => 1000,
            'amount' => 1000,
            'currency' => 'PHP',
        ]);

        $reservation->payments()->create([
            'amount' => 1000,
            'payment_mode' => 'cash',
            'status' => 'posted',
        ]);

        $reservation->refreshFinancialSummary();
        $reservation->refresh();

        $this->assertEquals('0.00', $reservation->addons_total);
        $this->assertEquals('1000.00', $reservation->payments_total);
        $this->assertEquals('0.00', $reservation->balance_due);
        $this->assertEquals('paid', $reservation->payment_status);
    }

    public function test_refresh_financial_summary_partially_paid(): void
    {
        $reservation = $this->createReservation();

        $reservation->charges()->create([
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'scope_id' => $reservation->id,
            'description' => 'Room charges',
            'qty' => 1,
            'unit_price' => 1000,
            'amount' => 1000,
            'currency' => 'PHP',
        ]);

        $reservation->payments()->create([
            'amount' => 500,
            'payment_mode' => 'cash',
            'status' => 'posted',
        ]);

        $reservation->refreshFinancialSummary();
        $reservation->refresh();

        $this->assertEquals('500.00', $reservation->payments_total);
        $this->assertEquals('500.00', $reservation->balance_due);
        $this->assertEquals('partially_paid', $reservation->payment_status);
    }

    public function test_refresh_financial_summary_pending(): void
    {
        $reservation = $this->createReservation();

        $reservation->refreshFinancialSummary();
        $reservation->refresh();

        $this->assertEquals('0.00', $reservation->payments_total);
        $this->assertEquals('0.00', $reservation->balance_due);
        $this->assertEquals('pending', $reservation->payment_status);
    }

    public function test_refresh_financial_summary_with_addons(): void
    {
        $reservation = $this->createReservation();

        $reservation->charges()->create([
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'scope_id' => $reservation->id,
            'description' => 'Room charges',
            'qty' => 1,
            'unit_price' => 1000,
            'amount' => 1000,
            'currency' => 'PHP',
        ]);

        $reservation->charges()->create([
            'charge_type' => 'addon',
            'scope_type' => 'reservation',
            'scope_id' => $reservation->id,
            'description' => 'Extra towels',
            'qty' => 2,
            'unit_price' => 50,
            'amount' => 100,
            'currency' => 'PHP',
        ]);

        $reservation->payments()->create([
            'amount' => 1100,
            'payment_mode' => 'cash',
            'status' => 'posted',
        ]);

        $reservation->refreshFinancialSummary();
        $reservation->refresh();

        $this->assertEquals('100.00', $reservation->addons_total);
        $this->assertEquals('paid', $reservation->payment_status);
    }
}
