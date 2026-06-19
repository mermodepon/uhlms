<?php

namespace App\Filament\Pages;

class ReservationSummaryReport extends Reports
{
    protected const REPORT_TYPE = 'reservation_summary';

    protected static ?string $slug = 'reports/reservation-summary';

    protected static ?string $navigationLabel = 'Reservation Summary';

    protected static ?int $navigationSort = 2;
}
