<?php

namespace App\Filament\Pages;

use App\Models\User;

class ReservationListReport extends Reports
{
    protected const REPORT_TYPE = 'reservation_list';

    protected const REPORT_PERMISSION = User::REPORT_RESERVATION_LIST_VIEW;

    protected static ?string $slug = 'reports/reservation-list';

    protected static ?string $navigationLabel = 'Reservation List';

    protected static ?int $navigationSort = 5;
}
