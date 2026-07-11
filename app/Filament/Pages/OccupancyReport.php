<?php

namespace App\Filament\Pages;

class OccupancyReport extends Reports
{
    protected const REPORT_TYPE = 'occupancy';

    protected static ?string $slug = 'reports/occupancy';

    protected static ?string $navigationLabel = 'Occupancy Report';

    protected static ?int $navigationSort = 6;
}
