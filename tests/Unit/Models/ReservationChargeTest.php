<?php

namespace Tests\Unit\Models;

use App\Models\ReservationCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $charge = new ReservationCharge;
        $fillable = $charge->getFillable();

        $this->assertContains('reservation_id', $fillable);
        $this->assertContains('charge_type', $fillable);
        $this->assertContains('amount', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('qty', $fillable);
        $this->assertContains('unit_price', $fillable);
    }

    public function test_casts(): void
    {
        $charge = new ReservationCharge;
        $casts = $charge->getCasts();

        $this->assertEquals('decimal:2', $casts['qty']);
        $this->assertEquals('decimal:2', $casts['unit_price']);
        $this->assertEquals('decimal:2', $casts['amount']);
        $this->assertEquals('array', $casts['meta']);
    }

    public function test_reservation_relationship(): void
    {
        $charge = new ReservationCharge;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $charge->reservation()
        );
    }

    public function test_creator_relationship(): void
    {
        $charge = new ReservationCharge;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $charge->creator()
        );
    }
}
