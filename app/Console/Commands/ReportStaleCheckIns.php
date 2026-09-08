<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportStaleCheckIns extends Command
{
    protected $signature = 'reservations:report-stale-checkins';

    protected $description = 'Report checked-in reservations whose checkout date has passed';

    public function handle(): int
    {
        $staleReservations = Reservation::query()
            ->where('status', 'checked_in')
            ->whereDate('check_out_date', '<=', today())
            ->orderBy('check_out_date')
            ->orderBy('id')
            ->get();

        foreach ($staleReservations as $reservation) {
            $openAssignmentCount = DB::table('room_assignments')
                ->where('reservation_id', $reservation->id)
                ->where('status', 'checked_in')
                ->count();

            $this->warn(
                "{$reservation->reference_number}: checkout date {$reservation->check_out_date->toDateString()} "
                    ."with {$openAssignmentCount} open assignment(s)."
            );
        }

        $this->info("{$staleReservations->count()} stale checked-in reservation(s) reported.");

        return self::SUCCESS;
    }
}
