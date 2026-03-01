<?php

namespace App\Providers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Observers\ReservationObserver;
use App\Observers\RoomObserver;
use App\Observers\RoomAssignmentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers for notification system
        Reservation::observe(ReservationObserver::class);
        Room::observe(RoomObserver::class);
        RoomAssignment::observe(RoomAssignmentObserver::class);
    }
}
