<?php

namespace App\Filament\Pages;

use App\Models\User;

class RoomUtilizationReport extends Reports
{
    protected const REPORT_TYPE = 'room_utilization';

    protected const REPORT_PERMISSION = User::REPORT_ROOM_UTILIZATION_VIEW;

    protected static ?string $slug = 'reports/room-utilization';

    protected static ?string $navigationLabel = 'Room Utilization';

    protected static ?int $navigationSort = 7;
}
