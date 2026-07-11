<?php

namespace App\Filament\Pages;

class StayLogsReport extends Reports
{
    protected const REPORT_TYPE = 'stay_logs';

    protected static ?string $slug = 'reports/stay-logs';

    protected static ?string $navigationLabel = 'Stay Logs';

    protected static ?int $navigationSort = 8;
}
