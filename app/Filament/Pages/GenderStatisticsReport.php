<?php

namespace App\Filament\Pages;

class GenderStatisticsReport extends Reports
{
    protected const REPORT_TYPE = 'gender_statistics';

    protected static ?string $slug = 'reports/gender-statistics';

    protected static ?string $navigationLabel = 'Gender Statistics';

    protected static ?int $navigationSort = 3;
}
