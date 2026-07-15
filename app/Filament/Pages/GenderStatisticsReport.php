<?php

namespace App\Filament\Pages;

use App\Models\User;

class GenderStatisticsReport extends Reports
{
    protected const REPORT_TYPE = 'gender_statistics';

    protected const REPORT_PERMISSION = User::REPORT_GENDER_STATISTICS_VIEW;

    protected static ?string $slug = 'reports/gender-statistics';

    protected static ?string $navigationLabel = 'Gender Statistics';

    protected static ?int $navigationSort = 3;
}
