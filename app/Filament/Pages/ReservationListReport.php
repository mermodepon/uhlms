<?php

namespace App\Filament\Pages;

class ReservationListReport extends Reports
{
    protected const REPORT_TYPE = 'reservation_list';

    protected static ?string $slug = 'reports/reservation-list';

    protected static ?string $navigationLabel = 'Reservation List';

    protected static ?int $navigationSort = 3;
}
