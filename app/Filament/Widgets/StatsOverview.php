<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\RoomResource;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
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
            $today = Carbon::today();
            $tomorrow = $today->copy()->addDay();

            // Consolidate room stats into a single query
            $roomStats = Room::where('is_active', true)
                ->select(DB::raw('COUNT(*) as total'))
                ->first();

            $totalRooms = $roomStats->total ?? 0;

            // Consolidate reservation stats into a single query (down from 4 queries)
            $reservationStats = Reservation::query()
                ->selectRaw(
                    "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'approved' AND DATE(check_in_date) = ? THEN 1 ELSE 0 END) as approved_today_checkins,
                    SUM(CASE WHEN status = 'confirmed' AND DATE(check_in_date) = ? THEN 1 ELSE 0 END) as confirmed_today_checkins,
                    SUM(CASE WHEN status = 'checked_in' AND DATE(check_out_date) = ? THEN 1 ELSE 0 END) as today_checkouts,
                    SUM(CASE WHEN status = 'checked_in' AND DATE(check_out_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) as near_due,
                    SUM(CASE WHEN status = 'checked_in' AND DATE(check_out_date) < ? THEN 1 ELSE 0 END) as overdue",
                    [
                        $today->toDateString(),
                        $today->toDateString(),
                        $today->toDateString(),
                        $today->toDateString(),
                        $tomorrow->toDateString(),
                        $today->toDateString(),
                    ],
                )
                ->first();

            // Active (checked-in) assignments in one query – only count active rooms
            $activeRoomIds = Room::where('is_active', true)->pluck('id');
            $stayStats = RoomAssignment::whereNotNull('checked_in_at')
                ->whereNull('checked_out_at')
                ->whereIn('room_id', $activeRoomIds)
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
                'nearDueReservations' => (int) ($reservationStats->near_due ?? 0),
                'overdueReservations' => (int) ($reservationStats->overdue ?? 0),
                'approvedReservations' => (int) ($reservationStats->approved ?? 0),
                'confirmedReservations' => (int) ($reservationStats->confirmed ?? 0),
                'approvedTodayCheckIns' => (int) ($reservationStats->approved_today_checkins ?? 0),
                'confirmedTodayCheckIns' => (int) ($reservationStats->confirmed_today_checkins ?? 0),
                'todayCheckOuts' => (int) ($reservationStats->today_checkouts ?? 0),
                'currentlyCheckedIn' => (int) ($stayStats->checked_in ?? 0),
            ];
        });

        $resourceIndex = ReservationResource::getUrl('index');
        $roomIndex = RoomResource::getUrl('index');

        $pendingUrl = $resourceIndex.'?status=pending';
        $nearDueUrl = $resourceIndex.'?near_due=1';
        $approvedUrl = $resourceIndex.'?status=approved';
        $confirmedUrl = $resourceIndex.'?status=confirmed';
        $checkedInUrl = $resourceIndex.'?status=checked_in';
        $overdueUrl = $resourceIndex.'?overdue=1';

        return [
            // Row 1: Action-required items
            Stat::make('Overdue Check-outs', $stats['overdueReservations'])
                ->description('Still checked in past check-out date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats['overdueReservations'] > 0 ? 'danger' : 'success')
                ->url($overdueUrl),

            Stat::make('Near Due', $stats['nearDueReservations'])
                ->description('Check-outs within 24 hours')
                ->descriptionIcon('heroicon-m-bell')
                ->color($stats['nearDueReservations'] > 0 ? 'warning' : 'success')
                ->url($nearDueUrl),

            Stat::make('Pending Reservations', $stats['pendingReservations'])
                ->description('Awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color(Color::hex('#fbbf24'))
                ->url($pendingUrl),

            // Row 2: Situational awareness
            Stat::make('Occupancy Rate', $stats['occupancyRate'].'%')
                ->description("{$stats['occupiedRooms']} of {$stats['totalRooms']} rooms occupied")
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($stats['occupancyRate'] > 80 ? 'success' : ($stats['occupancyRate'] > 50 ? 'warning' : 'danger'))
                ->chart([65, 70, 75, 80, 78, $stats['occupancyRate']])
                ->url($roomIndex.'?has_occupants=1'),

            Stat::make('Currently Checked In', $stats['currentlyCheckedIn'])
                ->description('Guests currently staying')
                ->descriptionIcon('heroicon-m-user-group')
                ->color(Color::hex('#16a34a'))
                ->url($checkedInUrl),

            Stat::make('Approved', $stats['approvedReservations'])
                ->description('Awaiting payment or confirmation')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color(Color::hex('#919F02'))
                ->url($approvedUrl),

            Stat::make('Confirmed (Awaiting Arrival)', $stats['confirmedReservations'])
                ->description("{$stats['confirmedTodayCheckIns']} expected check-ins today")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color(Color::hex('#10B981'))
                ->url($confirmedUrl),
        ];
    }
}
