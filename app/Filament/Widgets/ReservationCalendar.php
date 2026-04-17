<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ReservationCalendar extends FullCalendarWidget
{
    protected static string $view = 'filament.widgets.reservation-calendar';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * No model — we handle events manually via fetchEvents().
     */
    public string|null|Model $model = null;

    /**
     * Status-to-color mapping matching the existing legend / rest of the app.
     */
    protected static array $statusColors = [
        'pending'         => '#fbbf24', // amber
        'approved'        => '#3b82f6', // blue
        'pending_payment' => '#8b5cf6', // violet
        'checked_in'      => '#16a34a', // green
        'checked_out'     => '#94a3b8', // slate-gray
    ];

    /**
     * Which statuses are currently visible on the calendar.
     * Checked Out is OFF by default to reduce clutter.
     */
    public array $activeStatuses = ['pending', 'approved', 'confirmed', 'pending_payment', 'checked_in'];

    public function toggleStatus(string $status): void
    {
        if (in_array($status, $this->activeStatuses)) {
            $this->activeStatuses = array_values(array_diff($this->activeStatuses, [$status]));
        } else {
            $this->activeStatuses[] = $status;
        }

        $this->dispatch('filament-fullcalendar--refresh');
    }

    /**
     * FullCalendar configuration.
     */
    public function config(): array
    {
        return [
            'headerToolbar' => [
                'left'   => 'prev,next today',
                'center' => 'title',
                'right'  => 'dayGridMonth,dayGridWeek',
            ],
            'initialView'       => 'dayGridMonth',
            'dayMaxEvents'      => 4,
            'eventDisplay'      => 'block',
            'displayEventTime'  => false,
            'eventBorderColor'  => 'transparent',
            'height'            => 'auto',
            'firstDay'          => 0, // Sunday
        ];
    }

    /**
     * Fetch reservation events for the visible date range.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $start = $fetchInfo['start'];
        $end   = $fetchInfo['end'];

        $reservations = Reservation::query()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('check_in_date', [$start, $end])
                    ->orWhereBetween('check_out_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('check_in_date', '<=', $start)
                            ->where('check_out_date', '>=', $end);
                    });
            })
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->whereIn('status', $this->activeStatuses)
            ->get(['id', 'reference_number', 'guest_name', 'check_in_date', 'check_out_date', 'status']);

        return $reservations->map(function (Reservation $res) {
            $color = static::$statusColors[$res->status] ?? '#d1d5db';

            return EventData::make()
                ->id($res->id)
                ->title("{$res->reference_number} — {$res->guest_name}")
                ->start($res->check_in_date)
                ->end($res->check_out_date->copy()->addDay()) // FullCalendar end is exclusive
                ->backgroundColor($color)
                ->textColor('#ffffff')
                ->url(
                    url('/admin/reservations?tableSearch=' . urlencode($res->reference_number)),
                    shouldOpenUrlInNewTab: false,
                );
        })->toArray();
    }

    /**
     * Disable all interactive modal actions — the calendar is view/navigate only.
     */
    protected function headerActions(): array
    {
        return [];
    }
}
