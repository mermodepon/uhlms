<?php

namespace App\Providers;

use App\Models\Amenity;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use App\Observers\AmenityObserver;
use App\Observers\FloorObserver;
use App\Observers\ReservationObserver;
use App\Observers\RoomAssignmentObserver;
use App\Observers\RoomObserver;
use App\Observers\RoomTypeObserver;
use App\Observers\ServiceObserver;
use App\Policies\AmenityPolicy;
use App\Policies\FloorPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\RoomPolicy;
use App\Policies\RoomTypePolicy;
use App\Policies\ServicePolicy;
use App\Policies\UserPolicy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        // ── Global date picker defaults ─────────────────────────────
        // Sunday-first week (7) consistent across all calendars.
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->firstDayOfWeek(7));
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->firstDayOfWeek(7));

        // Register model observers for notification system
        Amenity::observe(AmenityObserver::class);
        Floor::observe(FloorObserver::class);
        Reservation::observe(ReservationObserver::class);
        Room::observe(RoomObserver::class);
        RoomAssignment::observe(RoomAssignmentObserver::class);
        RoomType::observe(RoomTypeObserver::class);
        Service::observe(ServiceObserver::class);

        // Register authorization policies
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(RoomType::class, RoomTypePolicy::class);
        Gate::policy(Floor::class, FloorPolicy::class);
        Gate::policy(Amenity::class, AmenityPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
