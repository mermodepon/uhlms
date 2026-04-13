<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SendNearDueReservationReminders::class,
        \App\Console\Commands\ReleaseExpiredRoomHolds::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run reservation reminders hourly (checks for near-due reservations)
        $schedule->command('reservation:remind-near-due')->hourly();

        // Release expired room holds every 15 minutes
        $schedule->command('room-holds:release-expired')->everyFifteenMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
