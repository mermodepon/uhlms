<?php

namespace App\Providers;

use App\Models\Amenity;
use App\Models\Message;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\Setting;
use App\Observers\AmenityObserver;
use App\Observers\MessageObserver;
use App\Observers\ReservationObserver;
use App\Observers\RoomObserver;
use App\Observers\RoomAssignmentObserver;
use App\Observers\RoomTypeObserver;
use App\Observers\SettingObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        // ── Production Eloquent optimizations ──────────────────────────
        // Prevent lazy loading in non-production to catch N+1 queries early;
        // log violations silently in production instead of crashing.
        Model::preventLazyLoading(! app()->isProduction());

        // Prevent silently discarding attributes not in $fillable
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Disable timestamps auto-updating on models that don't need it
        // (all current models use timestamps, so this is a safety net)
        Model::preventAccessingMissingAttributes(! app()->isProduction());

        // ── Production DB optimizations ────────────────────────────────
        if (app()->isProduction()) {
            // Disable query logging in production to save memory
            DB::disableQueryLog();
        }

        // Register model observers for notification system
        Amenity::observe(AmenityObserver::class);
        Message::observe(MessageObserver::class);
        Reservation::observe(ReservationObserver::class);
        Room::observe(RoomObserver::class);
        RoomAssignment::observe(RoomAssignmentObserver::class);
        RoomType::observe(RoomTypeObserver::class);
        Setting::observe(SettingObserver::class);
    }
}
