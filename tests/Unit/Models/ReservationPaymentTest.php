<?php

namespace Tests\Unit\Models;

use App\Models\ReservationPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $payment = new ReservationPayment;
        $fillable = $payment->getFillable();

        $this->assertContains('reservation_id', $fillable);
        $this->assertContains('amount', $fillable);
        $this->assertContains('payment_mode', $fillable);
        $this->assertContains('reference_no', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('received_by', $fillable);
    }

    public function test_casts(): void
    {
        $payment = new ReservationPayment;
        $casts = $payment->getCasts();

        $this->assertEquals('decimal:2', $casts['amount']);
        $this->assertEquals('datetime', $casts['received_at']);
        $this->assertEquals('array', $casts['meta']);
    }

    public function test_reservation_relationship(): void
    {
        $payment = new ReservationPayment;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $payment->reservation()
        );
    }

    public function test_received_by_user_relationship(): void
    {
        $payment = new ReservationPayment;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $payment->receivedByUser()
        );
    }
}
