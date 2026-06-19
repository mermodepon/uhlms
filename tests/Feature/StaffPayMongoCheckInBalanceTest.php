<?php

namespace Tests\Feature;

use App\Filament\Resources\ReservationResource\Pages\CheckInGuest;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPayMongoCheckInBalanceTest extends TestCase
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

        Setting::create(['key' => 'online_payments_enabled', 'value' => '1']);
        config([
            'paymongo.secret_key' => 'sk_test_example',
            'paymongo.strict_webhook_verification' => false,
        ]);
    }

    public function test_staff_can_generate_paymongo_checkout_for_remaining_checkin_balance(): void
    {
        $this->actingAs($this->createUser());
        [$reservation, $room] = $this->createReservationWithRoom();

        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 500,
            'payment_mode' => 'GCash',
            'gateway' => 'paymongo',
            'gateway_payment_id' => 'pay_deposit',
            'gateway_status' => 'paid',
            'is_deposit' => true,
            'status' => 'posted',
        ]);

        Http::fake([
            '*/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_checkin_balance',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/cs_checkin_balance',
                        'payment_intent' => ['id' => 'pi_checkin_balance'],
                        'payment_method_types' => ['gcash', 'card'],
                    ],
                ],
            ]),
        ]);

        Livewire::test(CheckInGuest::class, ['record' => $reservation])
            ->set('data.reservation_rooms', [[
                'room_mode' => 'private',
                'room_id' => $room->id,
                'includes_primary_guest' => true,
                'guests' => [],
            ]])
            ->set('data.detailed_checkin_datetime', $reservation->check_in_date->toDateString())
            ->set('data.detailed_checkout_datetime', $reservation->check_out_date->toDateString())
            ->call('createPayMongoBalanceCheckout');

        $payment = ReservationPayment::where('reservation_id', $reservation->id)
            ->where('meta->source', 'checkin_balance')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame('paymongo', $payment->gateway);
        $this->assertSame('pending', $payment->gateway_status);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('paymongo_online', $payment->payment_mode);
        $this->assertFalse($payment->is_deposit);
        $this->assertEquals('1500.00', (string) $payment->amount);
        $this->assertSame('cs_checkin_balance', $payment->gateway_source_id);
    }

    public function test_staff_cannot_generate_checkin_balance_checkout_when_online_payments_are_disabled(): void
    {
        Setting::where('key', 'online_payments_enabled')->update(['value' => '0']);

        $this->actingAs($this->createUser());
        [$reservation, $room] = $this->createReservationWithRoom();

        Livewire::test(CheckInGuest::class, ['record' => $reservation])
            ->set('data.reservation_rooms', [[
                'room_mode' => 'private',
                'room_id' => $room->id,
                'includes_primary_guest' => true,
                'guests' => [],
            ]])
            ->call('createPayMongoBalanceCheckout');

        $this->assertDatabaseMissing('reservation_payments', [
            'reservation_id' => $reservation->id,
            'gateway' => 'paymongo',
            'gateway_status' => 'pending',
        ]);
    }

    public function test_checkout_session_paid_webhook_posts_pending_checkin_balance_payment(): void
    {
        [$reservation] = $this->createReservationWithRoom();

        $this->createPendingCheckInBalancePayment($reservation);

        $payload = $this->checkoutSessionPaidPayload($reservation);

        $this->postJson(route('webhook.paymongo'), $payload)
            ->assertOk();

        $payment = ReservationPayment::where('reservation_id', $reservation->id)
            ->where('meta->source', 'checkin_balance')
            ->firstOrFail();

        $this->assertSame('posted', $payment->status);
        $this->assertSame('paid', $payment->gateway_status);
        $this->assertSame('PayMongo Online', $payment->payment_mode);
        $this->assertSame('pay_checkin_balance', $payment->gateway_payment_id);
        $this->assertSame('src_checkin_balance', $payment->gateway_source_id);
        $this->assertSame('PM-pay_checkin_balance', $payment->reference_no);
        $this->assertNotNull($payment->or_date);

        $reservation->refresh();
        $this->assertEquals('1500.00', (string) $reservation->payments_total);
    }

    public function test_staff_can_cancel_pending_paymongo_balance_checkout_and_unblock_manual_payment(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        [$reservation, $room] = $this->createReservationWithRoom();
        $payment = $this->createPendingCheckInBalancePayment($reservation);

        Livewire::test(CheckInGuest::class, ['record' => $reservation])
            ->set('data.reservation_rooms', [[
                'room_mode' => 'private',
                'room_id' => $room->id,
                'includes_primary_guest' => true,
                'guests' => [],
            ]])
            ->call('cancelPendingPayMongoBalanceCheckout');

        $payment->refresh();

        $this->assertSame('cancelled', $payment->status);
        $this->assertSame('cancelled', $payment->gateway_status);
        $this->assertSame($user->id, $payment->gateway_metadata['cancelled_by']);
        $this->assertSame('checkin_staff_action', $payment->gateway_metadata['cancellation_source']);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'event' => 'checkin_balance_payment_cancelled',
        ]);
    }

    public function test_cancelled_checkout_is_excluded_from_pending_total_and_new_checkout_can_be_generated(): void
    {
        $this->actingAs($this->createUser());
        [$reservation, $room] = $this->createReservationWithRoom();
        $oldPayment = $this->createPendingCheckInBalancePayment($reservation);
        $oldPayment->update([
            'status' => 'cancelled',
            'gateway_status' => 'cancelled',
        ]);

        Http::fake([
            '*/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_new_checkin_balance',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/cs_new_checkin_balance',
                        'payment_intent' => ['id' => 'pi_new_checkin_balance'],
                        'payment_method_types' => ['gcash', 'card'],
                    ],
                ],
            ]),
        ]);

        Livewire::test(CheckInGuest::class, ['record' => $reservation])
            ->set('data.reservation_rooms', [[
                'room_mode' => 'private',
                'room_id' => $room->id,
                'includes_primary_guest' => true,
                'guests' => [],
            ]])
            ->call('createPayMongoBalanceCheckout');

        $pendingPayments = ReservationPayment::where('reservation_id', $reservation->id)
            ->where('gateway_status', 'pending')
            ->where('status', 'pending')
            ->get();

        $this->assertCount(1, $pendingPayments);
        $this->assertSame('cs_new_checkin_balance', $pendingPayments->first()->gateway_source_id);
    }

    public function test_late_webhook_posts_cancelled_paymongo_checkout_and_flags_reconciliation(): void
    {
        [$reservation] = $this->createReservationWithRoom();
        $payment = $this->createPendingCheckInBalancePayment($reservation);
        $payment->update([
            'status' => 'cancelled',
            'gateway_status' => 'cancelled',
            'gateway_metadata' => array_merge($payment->gateway_metadata ?? [], [
                'cancelled_at' => now()->subMinute()->toIso8601String(),
                'cancelled_by' => 123,
            ]),
        ]);

        $this->postJson(route('webhook.paymongo'), $this->checkoutSessionPaidPayload($reservation))
            ->assertOk();

        $payment->refresh();

        $this->assertSame('posted', $payment->status);
        $this->assertSame('paid', $payment->gateway_status);
        $this->assertTrue($payment->gateway_metadata['paid_after_staff_cancellation']);
        $this->assertEquals('1500.00', (string) $reservation->fresh()->payments_total);
    }

    public function test_paid_paymongo_balance_checkout_cannot_be_cancelled(): void
    {
        $this->actingAs($this->createUser());
        [$reservation] = $this->createReservationWithRoom();
        $payment = $this->createPendingCheckInBalancePayment($reservation);
        $payment->update([
            'status' => 'posted',
            'gateway_status' => 'paid',
        ]);

        Livewire::test(CheckInGuest::class, ['record' => $reservation])
            ->call('cancelPendingPayMongoBalanceCheckout');

        $payment->refresh();

        $this->assertSame('posted', $payment->status);
        $this->assertSame('paid', $payment->gateway_status);
    }

    private function createPendingCheckInBalancePayment(Reservation $reservation): ReservationPayment
    {
        return ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 1500,
            'payment_mode' => 'paymongo_online',
            'gateway' => 'paymongo',
            'gateway_payment_id' => 'pi_checkin_balance',
            'gateway_source_id' => 'cs_checkin_balance',
            'gateway_status' => 'pending',
            'is_deposit' => false,
            'status' => 'pending',
            'gateway_metadata' => [
                'checkout_url' => 'https://checkout.paymongo.com/cs_checkin_balance',
            ],
            'meta' => [
                'source' => 'checkin_balance',
                'payment_type' => 'checkin_balance',
            ],
        ]);
    }

    private function checkoutSessionPaidPayload(Reservation $reservation): array
    {
        return [
            'data' => [
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'data' => [
                        'id' => 'cs_checkin_balance',
                        'attributes' => [
                            'metadata' => [
                                'reservation_id' => (string) $reservation->id,
                                'payment_type' => 'checkin_balance',
                            ],
                            'payments' => [[
                                'id' => 'pay_checkin_balance',
                                'attributes' => [
                                    'amount' => 150000,
                                    'status' => 'paid',
                                    'payment_intent_id' => 'pi_checkin_balance',
                                    'source' => [
                                        'id' => 'src_checkin_balance',
                                        'attributes' => ['type' => 'gcash'],
                                    ],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Staff User',
            'email' => 'staff-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);
    }

    /**
     * @return array{0: Reservation, 1: Room}
     */
    private function createReservationWithRoom(): array
    {
        $roomType = RoomType::create([
            'name' => 'Private '.uniqid(),
            'base_rate' => 1000,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $floor = Floor::create([
            'name' => 'Floor '.uniqid(),
            'level' => 1,
            'is_active' => true,
        ]);

        $room = Room::create([
            'room_number' => 'R'.uniqid(),
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'capacity' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_email' => 'john-'.uniqid().'@example.com',
            'guest_phone' => '09171234567',
            'preferred_room_type_id' => $roomType->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'number_of_occupants' => 1,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return [$reservation, $room];
    }
}
