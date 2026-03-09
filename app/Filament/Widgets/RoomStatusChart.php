<?php

namespace App\Filament\Widgets;

use App\Models\Room;
use App\Models\RoomAssignment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RoomStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Room Status Overview';

    protected static ?int $sort = 4;

    // Cache chart data and poll less aggressively
    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        // Cache for 60 seconds — consolidates 4 queries into 2
        $stats = Cache::remember('dashboard.room_status_chart', 60, function () {
            $rooms = Room::select(
                    DB::raw("SUM(CASE WHEN status = 'available' AND is_active = 1 THEN 1 ELSE 0 END) as available"),
                    DB::raw("SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance"),
                    DB::raw("SUM(CASE WHEN status = 'inactive' OR is_active = 0 THEN 1 ELSE 0 END) as inactive")
                )->first();

            $occupied = RoomAssignment::whereNotNull('checked_in_at')
                ->whereNull('checked_out_at')
                ->distinct('room_id')
                ->count('room_id');

            return [
                'available' => (int) ($rooms->available ?? 0),
                'occupied' => $occupied,
                'maintenance' => (int) ($rooms->maintenance ?? 0),
                'inactive' => (int) ($rooms->inactive ?? 0),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Rooms',
                    'data' => [$stats['available'], $stats['occupied'], $stats['maintenance'], $stats['inactive']],
                    'backgroundColor' => [
                        '#00491E', // CMU green - available
                        '#ef4444', // red - occupied
                        '#FFC600', // CMU yellow - maintenance
                        '#71717a', // zinc - inactive
                    ],
                ],
            ],
            'labels' => ['Available', 'Occupied', 'Maintenance', 'Inactive'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
