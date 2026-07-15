<?php

namespace App\Filament\Pages;

use App\Models\User;

class ReservationSummaryReport extends Reports
{
    protected const REPORT_TYPE = 'reservation_summary';

    protected const REPORT_PERMISSION = User::REPORT_RESERVATION_SUMMARY_VIEW;

    protected static ?string $slug = 'reports/reservation-summary';

    protected static ?string $navigationLabel = 'Reservation Summary';

    protected static ?int $navigationSort = 2;
}
