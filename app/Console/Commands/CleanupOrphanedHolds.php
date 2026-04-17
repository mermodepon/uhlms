<?php

namespace App\Console\Commands;

use App\Models\RoomHold;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holds:cleanup
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned short-term holds for checked-in/out reservations and expired holds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('🧹 Cleaning up orphaned room holds...');
        $this->newLine();

        // 1. Find short-term holds for already checked-in/out reservations
        $orphanedHolds = DB::table('room_holds')
            ->join('reservations', 'room_holds.reservation_id', '=', 'reservations.id')
            ->where('room_holds.hold_type', 'short_term')
            ->whereIn('reservations.status', ['checked_in', 'checked_out'])
            ->select('room_holds.id', 'room_holds.reservation_id', 'reservations.reference_number', 'reservations.status')
            ->get();

        if ($orphanedHolds->isNotEmpty()) {
            $this->warn("Found {$orphanedHolds->count()} orphaned short-term hold(s) for checked-in/out reservations:");
            
            foreach ($orphanedHolds as $hold) {
                $this->line("  • Hold ID {$hold->id} - Reservation {$hold->reference_number} (Status: {$hold->status})");
            }

            if (!$isDryRun) {
                $deleted = RoomHold::whereIn('id', $orphanedHolds->pluck('id'))->delete();
                $this->info("  ✅ Deleted {$deleted} orphaned hold(s)");
            } else {
                $this->comment("  [DRY RUN] Would delete {$orphanedHolds->count()} hold(s)");
            }
            
            $this->newLine();
        } else {
            $this->info('✓ No orphaned holds found for checked-in/out reservations');
            $this->newLine();
        }

        // 2. Find expired short-term holds
        $expiredHolds = RoomHold::query()
            ->where('hold_type', 'short_term')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredHolds->isNotEmpty()) {
            $this->warn("Found {$expiredHolds->count()} expired short-term hold(s):");
            
            foreach ($expiredHolds as $hold) {
                $reservation = $hold->reservation;
                $refNumber = $reservation ? $reservation->reference_number : 'N/A';
                $this->line("  • Hold ID {$hold->id} - Reservation {$refNumber} (Expired: {$hold->expires_at})");
            }

            if (!$isDryRun) {
                $deleted = $expiredHolds->each->delete();
                $this->info("  ✅ Deleted {$expiredHolds->count()} expired hold(s)");
            } else {
                $this->comment("  [DRY RUN] Would delete {$expiredHolds->count()} hold(s)");
            }
            
            $this->newLine();
        } else {
            $this->info('✓ No expired short-term holds found');
            $this->newLine();
        }

        $totalFound = $orphanedHolds->count() + $expiredHolds->count();

        if ($totalFound === 0) {
            $this->info('🎉 Database is clean! No orphaned or expired holds found.');
        } elseif ($isDryRun) {
            $this->comment("🔍 DRY RUN COMPLETE: {$totalFound} hold(s) would be deleted");
            $this->comment('Run without --dry-run to actually delete them');
        } else {
            $this->info("🎉 Cleanup complete! Removed {$totalFound} orphaned/expired hold(s)");
        }

        return Command::SUCCESS;
    }
}
