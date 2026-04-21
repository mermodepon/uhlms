<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Setting;
use App\Notifications\NotificationHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireUnpaidReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:expire-unpaid {--hours=72 : Hours after approval before expiring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-cancel approved reservations that remain unpaid after the payment deadline';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if online payments feature is enabled
        if (! Setting::isOnlinePaymentsEnabled()) {
            $this->info('⚠️  Online payments are currently disabled. Skipping auto-expiration.');
            $this->info('💡 Tip: Reservations will not be auto-cancelled while online payments are off.');
            return Command::SUCCESS;
        }

        $hoursLimit = (int) $this->option('hours');
        $cutoffTime = now()->subHours($hoursLimit);

        $this->info("Checking for approved reservations unpaid for more than {$hoursLimit} hours (approved before {$cutoffTime->format('Y-m-d H:i:s')})...");

        // Find approved reservations that:
        // 1. Have status 'approved'
        // 2. Were approved more than X hours ago
        // 3. Have zero payments
        $expiredReservations = Reservation::where('status', 'approved')
            ->whereNotNull('approved_at')
            ->where('approved_at', '<', $cutoffTime)
            ->where('payments_total', 0)
            ->get();

        if ($expiredReservations->isEmpty()) {
            $this->info('No unpaid reservations to expire.');
            return Command::SUCCESS;
        }

        $this->info("Found {$expiredReservations->count()} reservation(s) to expire.");

        $expiredCount = 0;

        foreach ($expiredReservations as $reservation) {
            DB::transaction(function () use ($reservation, $hoursLimit, &$expiredCount) {
                // Cancel the reservation
                $reservation->update([
                    'status' => 'cancelled',
                    'admin_notes' => trim(
                        ($reservation->admin_notes ?? '') . 
                        "\n\n[Auto-cancelled on " . now()->format('Y-m-d H:i') . 
                        "] Payment not received within {$hoursLimit} hours of approval."
                    ),
                ]);

                // Log the cancellation
                ReservationLog::record(
                    $reservation,
                    'auto_cancelled',
                    "Reservation auto-cancelled: Payment not received within {$hoursLimit} hours of approval.",
                    [
                        'approved_at' => $reservation->approved_at?->toIso8601String(),
                        'hours_limit' => $hoursLimit,
                    ]
                );

                // Notify guest if email exists
                if ($reservation->guest_email) {
                    try {
                        // TODO: Send email to guest about cancellation
                        // Mail::to($reservation->guest_email)->send(new ReservationCancelledMail($reservation));
                    } catch (\Exception $e) {
                        $this->error("Failed to email guest: {$e->getMessage()}");
                    }
                }

                // Notify staff
                NotificationHelper::notifyAllStaff(
                    'Reservation Auto-Cancelled',
                    "Reservation #{$reservation->reference_number} was automatically cancelled due to non-payment.",
                    'warning',
                    'auto_cancel',
                    url('/admin/reservations?tableSearch=' . urlencode($reservation->reference_number)),
                    null, // System action
                    'reservations_view'
                );

                $this->line("✓ Cancelled: {$reservation->reference_number} (approved " . $reservation->approved_at->diffForHumans() . ")");
                $expiredCount++;
            });
        }

        $this->info("\n✅ Expired {$expiredCount} reservation(s).");

        return Command::SUCCESS;
    }
}
