<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RollbackTestData extends Command
{
    protected $signature = 'rollback:test-data {--minutes=120}';

    protected $description = 'Remove test reservations, test room type and related notifications created during testing';

    public function handle()
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->subMinutes($minutes);

        DB::beginTransaction();
        try {
            $resQuery = Reservation::where(function ($q) {
                $q->where('guest_email', 'like', '%@example.test')
                  ->orWhere('guest_email', 'like', 'guest+%@example.test');
            })->where('created_at', '>=', now()->subMinutes((int) $this->option('minutes')));

            $resCount = $resQuery->count();
            $resQuery->delete();

            $notifQuery = Notification::where('message', 'like', 'Reservation #%')
                ->where('created_at', '>=', $cutoff);
            $notifCount = $notifQuery->count();
            $notifQuery->delete();

            $roomType = RoomType::where('name', 'Test Room Type')->first();
            $roomTypeDeleted = 0;
            if ($roomType && $roomType->created_at >= $cutoff) {
                $roomType->delete();
                $roomTypeDeleted = 1;
            }

            DB::commit();

            $this->info("Deleted {$resCount} test reservations.");
            $this->info("Deleted {$notifCount} test notifications.");
            if ($roomTypeDeleted) {
                $this->info('Deleted test RoomType.');
            } else {
                $this->info('No recent test RoomType removed.');
            }

            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Rollback failed: ' . $e->getMessage());
            return 1;
        }
    }
}
