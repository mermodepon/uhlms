<?php

namespace App\Filament\Pages;

use App\Models\User;

class StayLogsReport extends Reports
{
    protected const REPORT_TYPE = 'stay_logs';

    protected const REPORT_PERMISSION = User::REPORT_STAY_LOGS_VIEW;

    protected static ?string $slug = 'reports/stay-logs';

    protected static ?string $navigationLabel = 'Stay Logs';

    protected static ?int $navigationSort = 8;
}
