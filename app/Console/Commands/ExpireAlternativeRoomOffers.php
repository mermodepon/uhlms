<?php

namespace App\Console\Commands;

use App\Models\ReservationAlternativeOffer;
use App\Services\AlternativeRoomOfferService;
use Illuminate\Console\Command;

class ExpireAlternativeRoomOffers extends Command
{
    protected $signature = 'reservations:expire-alternative-offers';

    protected $description = 'Release expired alternative-room holds and return reservations to staff review';

    public function handle(): int
    {
        $offers = ReservationAlternativeOffer::query()
            ->where('status', ReservationAlternativeOffer::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($offers as $offer) {
            app(AlternativeRoomOfferService::class)->expireIfNeeded($offer);
        }

        $this->info("Expired {$offers->count()} alternative room offer(s).");

        return self::SUCCESS;
    }
}
