<?php

namespace Tests\Unit\Models;

use App\Models\ReservationAlternativeOffer;
use Tests\TestCase;

class ReservationAlternativeOfferTest extends TestCase
{
    public function test_offer_exposes_expected_statuses_and_casts(): void
    {
        $offer = new ReservationAlternativeOffer;

        $this->assertSame('pending', ReservationAlternativeOffer::STATUS_PENDING);
        $this->assertSame('accepted', ReservationAlternativeOffer::STATUS_ACCEPTED);
        $this->assertSame('declined', ReservationAlternativeOffer::STATUS_DECLINED);
        $this->assertSame('expired', ReservationAlternativeOffer::STATUS_EXPIRED);
        $this->assertSame('array', $offer->getCasts()['room_ids']);
        $this->assertSame('datetime', $offer->getCasts()['expires_at']);
    }
}
