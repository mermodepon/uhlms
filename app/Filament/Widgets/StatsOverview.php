<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\StayLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    // Cache stats and poll less aggressively in production
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // Cache dashboard stats for 60 seconds — avoids 7+ queries on every page load/poll
        $stats = Cache::remember('dashboard.stats_overview', 60, function () {
            // Consolidate room stats into a single query
            $roomStats = Room::where('is_active', true)
                ->select(DB::raw('COUNT(*) as total'))
                ->first();

            $totalRooms = $roomStats->total ?? 0;

            // Consolidate reservation stats into a single query (down from 4 queries)
            $reservationStats = Reservation::select(
                    DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                    DB::raw("SUM(CASE WHEN status IN ('approved', 'checked_in') THEN 1 ELSE 0 END) as active"),
                    DB::raw("SUM(CASE WHEN status = 'approved' AND check_in_date = CURDATE() THEN 1 ELSE 0 END) as today_checkins"),
                    DB::raw("SUM(CASE WHEN status = 'checked_in' AND check_out_date = CURDATE() THEN 1 ELSE 0 END) as today_checkouts")
                )
                ->first();

            // Active stay logs in one query (down from 2 queries)
            $stayStats = StayLog::whereNotNull('checked_in_at')
                ->whereNull('checked_out_at')
                ->select(
                    DB::raw('COUNT(*) as checked_in'),
                    DB::raw('COUNT(DISTINCT room_id) as occupied_rooms')
                )
                ->first();

            $occupiedRooms = $stayStats->occupied_rooms ?? 0;
            $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

            return [
                'totalRooms' => $totalRooms,
                'occupiedRooms' => $occupiedRooms,
                'occupancyRate' => $occupancyRate,
                'pendingReservations' => (int) ($reservationStats->pending ?? 0),
                'activeReservations' => (int) ($reservationStats->active ?? 0),
                'todayCheckIns' => (int) ($reservationStats->today_checkins ?? 0),
                'todayCheckOuts' => (int) ($reservationStats->today_checkouts ?? 0),
                'currentlyCheckedIn' => (int) ($stayStats->checked_in ?? 0),
            ];
        });

        return [
            Stat::make('Occupancy Rate', $stats['occupancyRate'] . '%')
                ->description("{$stats['occupiedRooms']} of {$stats['totalRooms']} rooms occupied")
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($stats['occupancyRate'] > 80 ? 'success' : ($stats['occupancyRate'] > 50 ? 'warning' : 'danger'))
                ->chart([65, 70, 75, 80, 78, $stats['occupancyRate']]),

            Stat::make('Pending Reservations', $stats['pendingReservations'])
                ->description('Awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Active Reservations', $stats['activeReservations'])
                ->description("{$stats['todayCheckIns']} check-ins today | {$stats['todayCheckOuts']} check-outs today")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Currently Checked In', $stats['currentlyCheckedIn'])
                ->description('Guests currently staying')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
        ];
    }
}
