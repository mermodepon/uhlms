<?php

namespace App\Console\Commands;

use App\Services\CheckInService;
use App\Services\RoomHoldService;
use Illuminate\Console\Command;

class ReleaseExpiredRoomHolds extends Command
{
    protected $signature = 'room-holds:release-expired';

    protected $description = 'Release expired short-term room holds and update affected room statuses';

    public function handle(CheckInService $checkInService, RoomHoldService $roomHoldService): int
    {
        $this->info('Releasing expired short-term room holds...');

        // Release expired holds from the existing check-in service
        $checkInCount = $checkInService->releaseExpiredHolds();
        if ($checkInCount > 0) {
            $this->info("Released {$checkInCount} expired check-in hold(s) from reservations.");
        }

        // Release any orphaned expired short-term room holds
        $holdCount = $roomHoldService->releaseExpiredHolds();
        if ($holdCount > 0) {
            $this->info("Released {$holdCount} orphaned expired short-term room hold(s).");
        }

        if ($checkInCount === 0 && $holdCount === 0) {
            $this->info('No expired holds found.');
        }

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
