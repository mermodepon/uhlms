<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Notifications\NotificationHelper;
use Illuminate\Console\Command;

class SendNearDueReservationReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservation:remind-near-due {hours=24 : Hours before due to remind}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for reservations where check-out is approaching within the specified hours';

    public function handle()
    {
        $hours = (int) $this->argument('hours');
        $now = now();
        $windowStart = $now;
        $windowEnd = $now->copy()->addHours($hours);

        // Find checked-in guests with check-out date approaching
        $nearDue = Reservation::where('status', 'checked_in')
            ->whereBetween('check_out_date', [$windowStart, $windowEnd])
            ->get();

        if ($nearDue->isEmpty()) {
            $this->info('No near due reservations found.');

            return 0;
        }

        foreach ($nearDue as $reservation) {
            $title = 'Check-Out Approaching';
            $message = sprintf(
                'Reservation #%s for %s is checking out soon on %s.',
                $reservation->reference_number,
                $reservation->guest_name,
                $reservation->check_out_date->format('M d, Y g:i A')
            );
            NotificationHelper::notifyAllStaff(
                $title,
                $message,
                'warning',
                'reservation',
                url('/admin/reservations/'.$reservation->id)
            );
        }

        $this->info('Reminders sent for '.$nearDue->count().' near due reservations.');

        return 0;
    }
}
