<?php

namespace App\Filament\Pages;

class RoomUtilizationReport extends Reports
{
    protected const REPORT_TYPE = 'room_utilization';

    protected static ?string $slug = 'reports/room-utilization';

    protected static ?string $navigationLabel = 'Room Utilization';

    protected static ?int $navigationSort = 5;
}
