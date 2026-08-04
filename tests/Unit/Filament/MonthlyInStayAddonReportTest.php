<?php

namespace Tests\Unit\Filament;

use App\Filament\Pages\Reports;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationPayment;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyInStayAddonReportTest extends TestCase
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

    public function test_monthly_report_includes_in_stay_addons_in_their_posting_month(): void
    {
        $month = Carbon::parse('2026-07-15 14:00:00');
        $roomType = RoomType::create([
            'name' => 'Report Room',
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $reservation = Reservation::create([
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'guest_email' => 'jane@example.test',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => '2026-06-30',
            'check_out_date' => '2026-07-03',
            'number_of_occupants' => 1,
            'status' => 'checked_out',
        ]);
        $charge = ReservationCharge::create([
            'reservation_id' => $reservation->id,
            'charge_type' => 'addon',
            'scope_type' => 'reservation',
            'scope_id' => $reservation->id,
            'description' => 'Late laundry request',
            'qty' => 1,
            'unit_price' => 100,
            'amount' => 100,
            'currency' => 'PHP',
            'meta' => ['source' => 'in_stay_addon'],
        ]);
        DB::table('reservation_charges')->where('id', $charge->id)->update([
            'created_at' => $month,
            'updated_at' => $month,
        ]);

        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 100,
            'payment_mode' => 'cash',
            'reference_no' => 'OR-REPORT-1',
            'or_date' => $month->toDateString(),
            'status' => 'posted',
            'received_at' => $month,
            'meta' => ['source' => 'checkout_settlement'],
        ]);

        $reports = new class extends Reports {
            public function monthlyData(): array
            {
                return $this->getMonthlyOrReport();
            }
        };
        $reports->monthPeriod = '2026-07';
        $data = $reports->monthlyData();

        $this->assertSame(100.0, $data['in_stay_addons_billed']);
        $this->assertSame(100.0, $data['payments_collected']);
        $this->assertSame(0.0, $data['outstanding_balance']);
        $this->assertSame('07/15/2026', $data['rows_by_date'][0]['date']);
        $this->assertSame('Late laundry request', $data['rows_by_date'][0]['rows'][0]['room_particulars']);
        $this->assertSame('OR-REPORT-1', $data['rows_by_date'][0]['rows'][0]['or_number']);
        $this->assertSame('07/15/2026', $data['rows_by_date'][0]['rows'][0]['or_date']);
    }

    public function test_monthly_report_includes_in_stay_extensions_in_their_posting_month(): void
    {
        $month = Carbon::parse('2026-07-18 14:00:00');
        $roomType = RoomType::create(['name' => 'Extension Room', 'base_rate' => 500, 'pricing_type' => 'flat_rate', 'room_sharing_type' => 'private', 'is_active' => true]);
        $reservation = Reservation::create(['guest_first_name' => 'Jane', 'guest_last_name' => 'Doe', 'guest_email' => 'extension@example.test', 'guest_phone' => '09171234567', 'preferred_room_type_id' => $roomType->id, 'check_in_date' => '2026-06-30', 'check_out_date' => '2026-07-03', 'number_of_occupants' => 1, 'status' => 'checked_out']);
        $charge = ReservationCharge::create(['reservation_id' => $reservation->id, 'charge_type' => 'room_rate', 'scope_type' => 'reservation', 'scope_id' => $reservation->id, 'description' => 'In-stay extension: Extension Room #1', 'qty' => 2, 'unit_price' => 500, 'amount' => 1000, 'currency' => 'PHP', 'meta' => ['source' => 'in_stay_extension']]);
        DB::table('reservation_charges')->where('id', $charge->id)->update(['created_at' => $month, 'updated_at' => $month]);

        $reports = new class extends Reports { public function monthlyData(): array { return $this->getMonthlyOrReport(); } };
        $reports->monthPeriod = '2026-07';
        $data = $reports->monthlyData();

        $this->assertSame(1000.0, $data['in_stay_extensions_billed']);
        $this->assertSame(0.0, $data['payments_collected']);
        $this->assertSame('07/18/2026', $data['rows_by_date'][0]['date']);
        $this->assertSame('—', $data['rows_by_date'][0]['rows'][0]['or_number']);
    }

    public function test_monthly_report_does_not_attach_a_settlement_that_predates_the_deferred_charge(): void
    {
        $roomType = RoomType::create(['name' => 'Timing Room', 'base_rate' => 500, 'pricing_type' => 'flat_rate', 'room_sharing_type' => 'private', 'is_active' => true]);
        $reservation = Reservation::create(['guest_first_name' => 'Jane', 'guest_last_name' => 'Doe', 'guest_email' => 'timing@example.test', 'guest_phone' => '09171234567', 'preferred_room_type_id' => $roomType->id, 'check_in_date' => '2026-07-01', 'check_out_date' => '2026-07-03', 'number_of_occupants' => 1, 'status' => 'checked_out']);
        ReservationPayment::create(['reservation_id' => $reservation->id, 'amount' => 100, 'payment_mode' => 'cash', 'reference_no' => 'OR-EARLY', 'or_date' => '2026-07-01', 'status' => 'posted', 'received_at' => '2026-07-01 10:00:00', 'meta' => ['source' => 'checkout_settlement']]);
        $charge = ReservationCharge::create(['reservation_id' => $reservation->id, 'charge_type' => 'addon', 'scope_type' => 'reservation', 'scope_id' => $reservation->id, 'description' => 'Later item', 'qty' => 1, 'unit_price' => 100, 'amount' => 100, 'currency' => 'PHP', 'meta' => ['source' => 'in_stay_addon']]);
        DB::table('reservation_charges')->where('id', $charge->id)->update(['created_at' => '2026-07-02 10:00:00', 'updated_at' => '2026-07-02 10:00:00']);

        $reports = new class extends Reports { public function monthlyData(): array { return $this->getMonthlyOrReport(); } };
        $reports->monthPeriod = '2026-07';
        $data = $reports->monthlyData();

        $this->assertSame('—', $data['rows_by_date'][0]['rows'][0]['or_number']);
    }
}
