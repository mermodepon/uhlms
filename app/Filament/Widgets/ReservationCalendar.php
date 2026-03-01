<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class ReservationCalendar extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.reservation-calendar';

    public int $currentMonth;
    public int $currentYear;

    public function mount(): void
    {
        $this->currentMonth = now()->month;
        $this->currentYear  = now()->year;
    }

    public function previousMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear  = $date->year;
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear  = $date->year;
    }

    public function getCalendarData(): array
    {
        $start = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // Fetch all reservations active within this month
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
            ->get(['id', 'reference_number', 'guest_name', 'check_in_date', 'check_out_date', 'status']);

        // Build a map: date string → list of reservation summaries
        $dayMap = [];
        foreach ($reservations as $res) {
            // Use max/min with copies to avoid mutating $start/$end
            $cursorDate = $res->check_in_date->copy();
            if ($cursorDate->lt($start)) {
                $cursorDate = $start->copy();
            }
            $finishDate = $res->check_out_date->copy();
            if ($finishDate->gt($end)) {
                $finishDate = $end->copy();
            }

            while ($cursorDate->lte($finishDate)) {
                $key = $cursorDate->toDateString();
                $dayMap[$key][] = [
                    'id'               => $res->id,
                    'guest_name'       => $res->guest_name,
                    'reference_number' => $res->reference_number,
                    'status'           => $res->status,
                    'is_checkin'       => $cursorDate->equalTo($res->check_in_date),
                    'is_checkout'      => $cursorDate->equalTo($res->check_out_date),
                ];
                $cursorDate->addDay();
            }
        }

        // Build weeks array — start from the Sunday on or before the 1st of the month
        $weeks   = [];
        $current = $start->copy()->subDays($start->dayOfWeek); // dayOfWeek: 0=Sun … 6=Sat

        // Run until we've passed the last day of the month
        while ($current->lte($end)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateStr = $current->toDateString();
                $week[] = [
                    'date'         => $current->copy(),
                    'in_month'     => (int) $current->month === (int) $this->currentMonth,
                    'is_today'     => $current->isToday(),
                    'reservations' => $dayMap[$dateStr] ?? [],
                ];
                $current->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'weeks'       => $weeks,
            'monthLabel'  => Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->format('F Y'),
        ];
    }

    protected function getViewData(): array
    {
        return $this->getCalendarData();
    }
}
