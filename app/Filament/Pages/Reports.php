<?php

namespace App\Filament\Pages;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reports';

    public string $reportType = 'monthly_or_report';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $reservationStatus = null;

    public ?string $monthPeriod = null;

    public int $monthlyReportPage = 1;

    public int $monthlyReportPerPage = 10;

    public int $reservationListPage = 1;

    public int $reservationListPerPage = 25;

    public int $occupancyPage = 1;

    public int $occupancyPerPage = 10;

    public int $roomUtilizationPage = 1;

    public int $roomUtilizationPerPage = 10;

    public int $stayLogsPage = 1;

    public int $stayLogsPerPage = 10;

    public function mount(): void
    {
        $this->dateFrom = Carbon::today()->subDays(30)->format('Y-m-d');
        $this->dateTo = Carbon::today()->format('Y-m-d');
        $this->reservationStatus = null; // All statuses
        $this->monthPeriod = Carbon::today()->format('Y-m');
    }

    public function updatedMonthPeriod(?string $value): void
    {
        $this->monthlyReportPage = 1;

        if (! $value) {
            return;
        }

        $monthStart = Carbon::createFromFormat('Y-m', $value)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Keep the date range in sync so print headers and period metadata stay consistent.
        $this->dateFrom = $monthStart->format('Y-m-d');
        $this->dateTo = $monthEnd->format('Y-m-d');
    }

    public function updatedReportType(): void
    {
        $this->monthlyReportPage = 1;
        $this->reservationListPage = 1;
        $this->occupancyPage = 1;
        $this->roomUtilizationPage = 1;
        $this->stayLogsPage = 1;
    }

    public function updatedDateFrom(): void
    {
        $this->reservationListPage = 1;
        $this->occupancyPage = 1;
        $this->roomUtilizationPage = 1;
        $this->stayLogsPage = 1;
    }

    public function updatedDateTo(): void
    {
        $this->reservationListPage = 1;
        $this->occupancyPage = 1;
        $this->roomUtilizationPage = 1;
        $this->stayLogsPage = 1;
    }

    public function updatedReservationStatus(): void
    {
        $this->reservationListPage = 1;
    }

    public function previousMonthlyReportPage(): void
    {
        $this->monthlyReportPage = max(1, $this->monthlyReportPage - 1);
    }

    public function nextMonthlyReportPage(): void
    {
        $this->monthlyReportPage++;
    }

    public function previousReservationListPage(): void
    {
        $this->reservationListPage = max(1, $this->reservationListPage - 1);
    }

    public function nextReservationListPage(): void
    {
        $this->reservationListPage++;
    }

    public function previousOccupancyPage(): void
    {
        $this->occupancyPage = max(1, $this->occupancyPage - 1);
    }

    public function nextOccupancyPage(): void
    {
        $this->occupancyPage++;
    }

    public function previousRoomUtilizationPage(): void
    {
        $this->roomUtilizationPage = max(1, $this->roomUtilizationPage - 1);
    }

    public function nextRoomUtilizationPage(): void
    {
        $this->roomUtilizationPage++;
    }

    public function previousStayLogsPage(): void
    {
        $this->stayLogsPage = max(1, $this->stayLogsPage - 1);
    }

    public function nextStayLogsPage(): void
    {
        $this->stayLogsPage++;
    }

    public function getReportDataProperty(): array
    {
        return match ($this->reportType) {
            'reservation_summary' => $this->getReservationSummary(),
            'occupancy' => $this->getOccupancyReport(),
            'room_utilization' => $this->getRoomUtilization(),
            'stay_logs' => $this->getStayLogs(),
            'reservation_list' => $this->getReservationList(),
            'monthly_or_report' => $this->getMonthlyOrReport(),
            default => [],
        };
    }

    protected function getMonthlyOrReport(): array
    {
        $month = $this->monthPeriod
            ? Carbon::createFromFormat('Y-m', $this->monthPeriod)
            : Carbon::today();

        $from = $month->copy()->startOfMonth()->startOfDay();
        $to = $month->copy()->endOfMonth()->endOfDay();

        // Keep shared period values aligned with this report's selected month.
        $this->dateFrom = $from->format('Y-m-d');
        $this->dateTo = $to->format('Y-m-d');

        // Get all reservations with check-ins in this month
        $reservations = Reservation::query()
            ->with([
                'roomAssignments' => function ($q) use ($from, $to) {
                    $q->whereNotNull('checked_in_at')
                        ->whereBetween('checked_in_at', [$from, $to])
                        ->with('room.roomType');
                },
                'payments',
                'guests',
                'checkInSnapshots',
                'charges',
            ])
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereHas('roomAssignments', function ($q) use ($from, $to) {
                $q->whereNotNull('checked_in_at')
                    ->whereBetween('checked_in_at', [$from, $to]);
            })
            ->get();

        $rowsByDate = [];
        $totalDomesticMale = 0;
        $totalDomesticFemale = 0;
        $totalInternationalMale = 0;
        $totalInternationalFemale = 0;
        $grandTotal = 0;

        foreach ($reservations as $reservation) {
            $assignments = $reservation->roomAssignments
                ->filter(function ($assignment) use ($from, $to) {
                    if (! $assignment->checked_in_at) {
                        return false;
                    }

                    return Carbon::parse($assignment->checked_in_at)->betweenIncluded($from, $to);
                })
                ->values();

            if ($assignments->isEmpty()) {
                continue;
            }

            $firstAssignment = $assignments->first();
            $orDate = $firstAssignment->or_date
                ? Carbon::parse($firstAssignment->or_date)
                : Carbon::parse($firstAssignment->checked_in_at);

            $dateKey = $orDate->toDateString();

            // Calculate number of nights (by calendar date, not exact hours)
            $checkInDate = Carbon::parse($firstAssignment->checked_in_at ?? $reservation->check_in_date)->startOfDay();
            $checkOutDate = $firstAssignment->checked_out_at
                ? Carbon::parse($firstAssignment->checked_out_at)->startOfDay()
                : Carbon::parse($reservation->check_out_date)->startOfDay();
            $nights = max(1, (int) $checkInDate->diffInDays($checkOutDate));

            // Guest name (lastname first)
            $guestName = trim(($reservation->guest_last_name ?? '').', '.($reservation->guest_first_name ?? ''));
            if (empty(trim($guestName, ', '))) {
                $guestName = $reservation->guest_name ?? 'Unknown Guest';
            }

            // Discount / ID info
            $snapshot = $reservation->checkInSnapshots->sortByDesc('id')->first();
            $discountCharge = $reservation->charges->where('charge_type', 'discount')->sortByDesc('id')->first();
            $hasDiscount = $discountCharge !== null;
            $guestIdNumber = $hasDiscount ? ($snapshot?->id_number ?? '') : '';

            // Common OR info for all lines of this reservation
            $orNumber = $firstAssignment->payment_or_number ?? '-';
            $orDateFormatted = $orDate->format('m/d/Y');
            $rfNumber = $reservation->reference_number ?? '-';

            // Count overall pax (for date-group subtotals and report footer)
            $domesticMale = 0;
            $domesticFemale = 0;
            $internationalMale = 0;
            $internationalFemale = 0;
            foreach ($assignments as $assignment) {
                $gender = $assignment->guest_gender ?? $reservation->guest_gender ?? 'Other';
                $nationality = $assignment->nationality ?? 'Filipino';
                $isDomestic = stripos($nationality, 'filipino') !== false || stripos($nationality, 'philippine') !== false;
                if ($isDomestic && strtolower($gender) === 'male') {
                    $domesticMale++;
                } elseif ($isDomestic && strtolower($gender) === 'female') {
                    $domesticFemale++;
                } elseif (! $isDomestic && strtolower($gender) === 'male') {
                    $internationalMale++;
                } elseif (! $isDomestic && strtolower($gender) === 'female') {
                    $internationalFemale++;
                }
            }
            $totalDomesticMale += $domesticMale;
            $totalDomesticFemale += $domesticFemale;
            $totalInternationalMale += $internationalMale;
            $totalInternationalFemale += $internationalFemale;
            $maleCount = $domesticMale + $internationalMale;
            $femaleCount = $domesticFemale + $internationalFemale;

            // Amount actually paid (OR total for this reservation)
            $amountPaid = (float) ($firstAssignment->payment_amount ?? 0);
            $grandTotal += $amountPaid;

            // ---- Build per-line sub-rows ----
            $reservationLines = [];
            $isFirstLine = true;

            // One line per unique room
            foreach ($assignments->unique('room_id') as $asgmt) {
                $room = $asgmt->room;
                $roomType = $room?->roomType ?? null;
                if (! $room || ! $roomType) {
                    continue;
                }

                $rate = (float) $roomType->base_rate;

                // Pax in this specific room
                $roomAsgmts = $assignments->where('room_id', $room->id);
                $roomMale = 0;
                $roomFemale = 0;
                foreach ($roomAsgmts as $ra) {
                    $g = strtolower($ra->guest_gender ?? $reservation->guest_gender ?? '');
                    if ($g === 'male') {
                        $roomMale++;
                    } elseif ($g === 'female') {
                        $roomFemale++;
                    }
                }

                // Line amount
                if ($roomType->pricing_type === 'per_person') {
                    $guestCount = max(1, $roomAsgmts->count());
                    $lineAmount = $rate * $guestCount * $nights;
                } else {
                    $lineAmount = $rate * $nights;
                }

                $reservationLines[] = [
                    'guest_name' => $isFirstLine ? $guestName : '***',
                    'guest_id_number' => $isFirstLine ? $guestIdNumber : '',
                    'nights' => $nights,
                    'room_particulars' => $roomType->name.' #'.$room->room_number,
                    'rate' => number_format($rate, 2),
                    'male_count' => $roomMale,
                    'female_count' => $roomFemale,
                    'rf_number' => $rfNumber,
                    'amount' => $lineAmount,
                    'or_number' => $orNumber,
                    'or_date' => $orDateFormatted,
                    'total' => null,
                    'show_total' => false,
                ];
                $isFirstLine = false;
            }

            // One line per add-on charge
            foreach ($reservation->charges->where('charge_type', 'addon') as $charge) {
                $qty = (int) max(1, $charge->qty ?? 1);
                // Strip leading multiplier prefix (e.g. "3x ") so the Particulars column isn't redundant with the Qty column
                $particulars = preg_replace('/^\d+x\s+/', '', $charge->description);
                $reservationLines[] = [
                    'guest_name' => '***',
                    'guest_id_number' => '',
                    'nights' => $qty,
                    'room_particulars' => $particulars,
                    'rate' => number_format((float) $charge->unit_price, 2),
                    'male_count' => null,
                    'female_count' => null,
                    'rf_number' => $rfNumber,
                    'amount' => (float) $charge->amount,
                    'or_number' => $orNumber,
                    'or_date' => $orDateFormatted,
                    'total' => null,
                    'show_total' => false,
                ];
            }

            // If no lines were built (no room data), add a fallback line
            if (empty($reservationLines)) {
                $reservationLines[] = [
                    'guest_name' => $guestName,
                    'guest_id_number' => $guestIdNumber,
                    'nights' => $nights,
                    'room_particulars' => '-',
                    'rate' => '-',
                    'male_count' => $maleCount,
                    'female_count' => $femaleCount,
                    'rf_number' => $rfNumber,
                    'amount' => $amountPaid,
                    'or_number' => $orNumber,
                    'or_date' => $orDateFormatted,
                    'total' => null,
                    'show_total' => false,
                ];
            }

            // Total (payment amount) shown only on the first line
            $reservationLines[0]['total'] = $amountPaid;
            $reservationLines[0]['show_total'] = true;

            // Add all lines to the date group
            if (! isset($rowsByDate[$dateKey])) {
                $rowsByDate[$dateKey] = [
                    'date' => $orDate->format('m/d/Y'),
                    'date_sort' => $dateKey,
                    'rows' => [],
                    'total_male' => 0,
                    'total_female' => 0,
                    'total_amount' => 0,
                ];
            }

            foreach ($reservationLines as $line) {
                $rowsByDate[$dateKey]['rows'][] = $line;
            }

            $rowsByDate[$dateKey]['total_male'] += $maleCount;
            $rowsByDate[$dateKey]['total_female'] += $femaleCount;
            $rowsByDate[$dateKey]['total_amount'] += $amountPaid;
        }

        // Sort by date
        $rowsByDate = collect($rowsByDate)
            ->sortBy('date_sort')
            ->values()
            ->toArray();

        $totalPax = $totalDomesticMale + $totalDomesticFemale + $totalInternationalMale + $totalInternationalFemale;

        return [
            'type' => 'monthly_or_report',
            'month_label' => $month->format('F Y'),
            'rows_by_date' => $rowsByDate,
            'grand_total' => $grandTotal,
            'total_domestic_male' => $totalDomesticMale,
            'total_domestic_female' => $totalDomesticFemale,
            'total_international_male' => $totalInternationalMale,
            'total_international_female' => $totalInternationalFemale,
            'total_male' => $totalDomesticMale + $totalInternationalMale,
            'total_female' => $totalDomesticFemale + $totalInternationalFemale,
            'total_pax' => $totalPax,
        ];
    }

    protected function getReservationSummary(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $reservations = Reservation::with('preferredRoomType')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in_date', [$from, $to])
                    ->orWhereBetween('check_out_date', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('check_in_date', '<=', $from)
                            ->where('check_out_date', '>=', $to);
                    });
            })
            ->get();

        $byStatus = $reservations->groupBy('status')->map->count();
        $byPurpose = $reservations->groupBy('purpose')->map->count();
        $byRoomType = $reservations
            ->map(fn ($reservation) => $reservation->preferredRoomType?->name)
            ->filter()
            ->countBy()
            ->toArray();

        $totalNights = $reservations->whereIn('status', ['checked_in', 'checked_out'])->sum(function ($r) {
            $nights = Carbon::parse($r->check_in_date)->diffInDays(Carbon::parse($r->check_out_date));

            return $nights * max(1, (int) $r->number_of_occupants);
        });

        return [
            'type' => 'reservation_summary',
            'total' => $reservations->count(),
            'by_status' => $byStatus->toArray(),
            'by_purpose' => $byPurpose->toArray(),
            'by_room_type' => $byRoomType,
            'total_guest_nights' => $totalNights,
            'avg_occupants' => round($reservations->avg('number_of_occupants') ?? 0, 1),
        ];
    }

    protected function getOccupancyReport(): array
    {
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);
        $roomsByStatus = Room::where('is_active', true)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalRooms = (int) $roomsByStatus->sum();
        $occupiedNow = (int) ($roomsByStatus['occupied'] ?? 0);
        $maintenanceNow = (int) ($roomsByStatus['maintenance'] ?? 0);

        $assignments = RoomAssignment::query()
            ->whereNotNull('checked_in_at')
            ->where('checked_in_at', '<=', $to->copy()->endOfDay())
            ->where(function ($q) use ($from) {
                $q->whereNull('checked_out_at')
                    ->orWhere('checked_out_at', '>=', $from->copy()->startOfDay());
            })
            ->get(['checked_in_at', 'checked_out_at']);

        // Daily occupancy for chart (last 30 days)
        $dailyOccupancy = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dateStart = $date->copy()->startOfDay();
            $dateEnd = $date->copy()->endOfDay();
            $occupied = $assignments->filter(function ($assignment) use ($dateStart, $dateEnd) {
                $checkedIn = Carbon::parse($assignment->checked_in_at);
                $checkedOut = $assignment->checked_out_at ? Carbon::parse($assignment->checked_out_at) : null;

                return $checkedIn->lte($dateEnd) && ($checkedOut === null || $checkedOut->gte($dateStart));
            })->count();

            $dailyOccupancy[] = [
                'date' => $date->format('M d'),
                'occupied' => $occupied,
                'rate' => $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0,
            ];
        }

        return [
            'type' => 'occupancy',
            'total_rooms' => $totalRooms,
            'occupied_now' => $occupiedNow,
            'maintenance_now' => $maintenanceNow,
            'available_now' => $totalRooms - $occupiedNow - $maintenanceNow,
            'current_rate' => $totalRooms > 0 ? round(($occupiedNow / $totalRooms) * 100, 1) : 0,
            'daily' => $dailyOccupancy,
        ];
    }

    protected function getRoomUtilization(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();
        $totalDays = max(1, $from->diffInDays($to->copy()->startOfDay()) + 1); // inclusive day count

        $rooms = Room::with(['roomType', 'roomAssignments' => function ($q) use ($from, $to) {
            // Only load assignments that overlap with the selected period
            $q->whereNotNull('checked_in_at')
                ->where(function ($q) use ($from, $to) {
                    $q->where('checked_in_at', '<=', $to)
                        ->where(function ($q) use ($from) {
                            $q->whereNull('checked_out_at')
                                ->orWhere('checked_out_at', '>=', $from);
                        });
                });
        }])->where('is_active', true)->get();

        $utilization = $rooms->map(function ($room) use ($from, $to, $totalDays) {
            $daysOccupied = $room->roomAssignments->sum(function ($assign) use ($from, $to) {
                $checkIn = Carbon::parse($assign->checked_in_at)->startOfDay();
                // Still checked in → count through end of today
                $checkOut = $assign->checked_out_at
                    ? Carbon::parse($assign->checked_out_at)->startOfDay()
                    : Carbon::today()->addDay(); // include today as an occupied day

                // Clamp to the report period
                $start = $checkIn->greaterThan($from) ? $checkIn : $from->copy();
                $end = $checkOut->lessThan($to) ? $checkOut : $to->copy()->startOfDay()->addDay();

                // No overlap → 0
                if ($start->greaterThanOrEqualTo($end)) {
                    return 0;
                }

                return (int) $start->diffInDays($end);
            });

            return [
                'room' => $room->room_number,
                'type' => $room->roomType->name ?? 'N/A',
                'status' => $room->status,
                'days_occupied' => $daysOccupied,
                'utilization_rate' => round(($daysOccupied / $totalDays) * 100, 1),
            ];
        })->sortByDesc('utilization_rate')->values()->toArray();

        // By room type
        $stayCountsByRoomType = RoomAssignment::query()
            ->join('rooms', 'room_assignments.room_id', '=', 'rooms.id')
            ->whereBetween('room_assignments.checked_in_at', [$from, $to])
            ->select('rooms.room_type_id as room_type_id', DB::raw('count(*) as total'))
            ->groupBy('rooms.room_type_id')
            ->pluck('total', 'room_type_id');

        $byType = RoomType::withCount(['rooms' => function ($q) {
            $q->where('is_active', true);
        }])->get()->map(function ($type) use ($stayCountsByRoomType) {
            $stayCount = (int) ($stayCountsByRoomType[$type->id] ?? 0);

            return [
                'name' => $type->name,
                'room_count' => $type->rooms_count,
                'total_stays' => $stayCount,
            ];
        })->toArray();

        return [
            'type' => 'room_utilization',
            'rooms' => $utilization,
            'by_type' => $byType,
        ];
    }

    protected function getStayLogs(): array
    {
        // still named getStayLogs for compatibility with the resource, but data
        // now comes from RoomAssignment so that we can eventually remove the
        // stay_logs table altogether.
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $logs = RoomAssignment::with(['reservation', 'room.roomType', 'assignedByUser', 'checkedOutByUser'])
            ->whereBetween('checked_in_at', [$from, $to])
            ->orderByDesc('checked_in_at')
            ->get()
            ->map(function ($assign) {
                return [
                    'guest' => $assign->reservation->guest_name ?? 'N/A',
                    'reference' => $assign->reservation->reference_number ?? 'N/A',
                    'room' => $assign->room->room_number ?? 'N/A',
                    'room_type' => $assign->room->roomType->name ?? 'N/A',
                    'checked_in' => $assign->checked_in_at ? Carbon::parse($assign->checked_in_at)->format('M d, Y') : '-',
                    'checked_out' => $assign->checked_out_at ? Carbon::parse($assign->checked_out_at)->format('M d, Y') : 'Still checked in',
                    'checked_in_by' => $assign->assignedByUser->name ?? '-',
                    'checked_out_by' => optional($assign->checkedOutByUser)->name ?? '-',
                    // Ensure nights is always an integer (date-only diff)
                    'nights' => $assign->checked_out_at
                        ? (int) Carbon::parse($assign->checked_in_at)->startOfDay()->diffInDays(Carbon::parse($assign->checked_out_at)->startOfDay())
                        : (int) Carbon::parse($assign->checked_in_at)->startOfDay()->diffInDays(Carbon::now()->startOfDay()),
                    'remarks' => $assign->remarks ?? '-',
                ];
            });

        return [
            'type' => 'stay_logs',
            'logs' => $logs->toArray(),
            'total_stays' => count($logs),
            'completed' => $logs->where('checked_out', '!=', 'Still checked in')->count(),
            'ongoing' => $logs->where('checked_out', 'Still checked in')->count(),
        ];
    }

    protected function getReservationList(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $query = Reservation::with(['preferredRoomType', 'roomAssignments.room.roomType'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in_date', [$from, $to])
                    ->orWhereBetween('check_out_date', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('check_in_date', '<=', $from)
                            ->where('check_out_date', '>=', $to);
                    });
            });

        // Apply status filter if specified
        if ($this->reservationStatus) {
            $query->where('status', $this->reservationStatus);
        }

        $reservations = $query->orderBy('check_in_date', 'desc')
            ->get()
            ->map(function ($reservation) {
                $assignedRooms = $reservation->roomAssignments->map(fn ($assignment) => $assignment->room->room_number)->join(', ');
                $nights = Carbon::parse($reservation->check_in_date)->diffInDays(Carbon::parse($reservation->check_out_date));

                return [
                    'reference' => $reservation->reference_number,
                    'guest_name' => $reservation->guest_name,
                    'guest_email' => $reservation->guest_email,
                    'guest_phone' => $reservation->guest_phone,
                    'check_in_date' => Carbon::parse($reservation->check_in_date)->format('M d, Y'),
                    'check_out_date' => Carbon::parse($reservation->check_out_date)->format('M d, Y'),
                    'nights' => $nights,
                    'occupants' => $reservation->number_of_occupants,
                    'preferred_room_type' => $reservation->preferredRoomType->name ?? 'N/A',
                    'assigned_rooms' => $assignedRooms ?: 'Not assigned',
                    'purpose' => $reservation->purpose,
                    'status' => $reservation->status,
                    'created_at' => Carbon::parse($reservation->created_at)->format('M d, Y'),
                ];
            })->toArray();

        $byStatus = collect($reservations)->groupBy('status')->map->count()->toArray();

        return [
            'type' => 'reservation_list',
            'reservations' => $reservations,
            'total' => count($reservations),
            'by_status' => $byStatus,
        ];
    }
}
