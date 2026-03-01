<?php

namespace App\Filament\Widgets;

use App\Models\Room;
use Filament\Widgets\ChartWidget;

class RoomStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Room Status Overview';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $available = Room::where('status', 'available')->where('is_active', true)->count();
        // Count rooms with active stay logs (checked in, not checked out)
        $occupied = \App\Models\StayLog::whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->distinct('room_id')
            ->count('room_id');
        $maintenance = Room::where('status', 'maintenance')->count();
        $inactive = Room::where('status', 'inactive')->orWhere('is_active', false)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Rooms',
                    'data' => [$available, $occupied, $maintenance, $inactive],
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
