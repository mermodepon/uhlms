<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\StayLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalRooms = Room::where('is_active', true)->count();
        // Count rooms with active stay logs (checked in, not checked out)
        $occupiedRooms = StayLog::whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->distinct('room_id')
            ->count('room_id');
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        $pendingReservations = Reservation::where('status', 'pending')->count();
        $activeReservations = Reservation::whereIn('status', ['approved', 'checked_in'])->count();

        $todayCheckIns = Reservation::where('status', 'approved')
            ->whereDate('check_in_date', today())
            ->count();

        $todayCheckOuts = Reservation::where('status', 'checked_in')
            ->whereDate('check_out_date', today())
            ->count();

        $currentlyCheckedIn = StayLog::whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->count();

        return [
            Stat::make('Occupancy Rate', $occupancyRate . '%')
                ->description("{$occupiedRooms} of {$totalRooms} rooms occupied")
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($occupancyRate > 80 ? 'success' : ($occupancyRate > 50 ? 'warning' : 'danger'))
                ->chart([65, 70, 75, 80, 78, $occupancyRate]),

            Stat::make('Pending Reservations', $pendingReservations)
                ->description('Awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Active Reservations', $activeReservations)
                ->description("{$todayCheckIns} check-ins today | {$todayCheckOuts} check-outs today")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Currently Checked In', $currentlyCheckedIn)
                ->description('Guests currently staying')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
        ];
    }
}
