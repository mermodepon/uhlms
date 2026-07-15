<?php

namespace App\Filament\Pages;

use App\Models\User;

class OccupancyReport extends Reports
{
    protected const REPORT_TYPE = 'occupancy';

    protected const REPORT_PERMISSION = User::REPORT_OCCUPANCY_VIEW;

    protected static ?string $slug = 'reports/occupancy';

    protected static ?string $navigationLabel = 'Occupancy Report';

    protected static ?int $navigationSort = 6;
}
