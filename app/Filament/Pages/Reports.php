<?php

namespace App\Filament\Pages;

use App\Models\Reservation;
use App\Models\Room;
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

    public string $reportType = 'reservation_summary';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public ?string $reservationStatus = null;

    public function mount(): void
    {
        $this->dateFrom = Carbon::today()->subDays(30)->format('Y-m-d');
        $this->dateTo = Carbon::today()->format('Y-m-d');
        $this->reservationStatus = null; // All statuses
    }

    public function getReportDataProperty(): array
    {
        return match ($this->reportType) {
            'reservation_summary' => $this->getReservationSummary(),
            'occupancy' => $this->getOccupancyReport(),
            'room_utilization' => $this->getRoomUtilization(),
            'stay_logs' => $this->getStayLogs(),
            'reservation_list' => $this->getReservationList(),
            default => [],
        };
    }

    protected function getReservationSummary(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $reservations = Reservation::where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in_date', [$from, $to])
                  ->orWhereBetween('check_out_date', [$from, $to])
                  ->orWhere(function ($q2) use ($from, $to) {
                      $q2->where('check_in_date', '<=', $from)
                         ->where('check_out_date', '>=', $to);
                  });
            })->get();

        $byStatus = $reservations->groupBy('status')->map->count();
        $byPurpose = $reservations->groupBy('purpose')->map->count();
        $byRoomType = Reservation::where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in_date', [$from, $to])
                  ->orWhereBetween('check_out_date', [$from, $to])
                  ->orWhere(function ($q2) use ($from, $to) {
                      $q2->where('check_in_date', '<=', $from)
                         ->where('check_out_date', '>=', $to);
                  });
            })
            ->join('room_types', 'reservations.preferred_room_type_id', '=', 'room_types.id')
            ->select('room_types.name', DB::raw('count(*) as total'))
            ->groupBy('room_types.name')
            ->pluck('total', 'name')
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
        $totalRooms = Room::where('is_active', true)->count();
        $occupiedNow = Room::where('status', 'occupied')->count();
        $maintenanceNow = Room::where('status', 'maintenance')->count();

        // Daily occupancy for chart (last 30 days)
        $dailyOccupancy = [];
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $occupied = RoomAssignment::where('checked_in_at', '<=', $date->copy()->endOfDay())
                ->where(function ($q) use ($date) {
                    $q->whereNull('checked_out_at')
                      ->orWhere('checked_out_at', '>=', $date->copy()->startOfDay());
                })
                ->count();

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
        $rooms = Room::with(['roomType', 'roomAssignments'])->where('is_active', true)->get();
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);
        $totalDays = $from->diffInDays($to) ?: 1;

        $utilization = $rooms->map(function ($room) use ($from, $to, $totalDays) {
            $daysOccupied = $room->roomAssignments->sum(function ($assign) use ($from, $to) {
                $checkIn = Carbon::parse($assign->checked_in_at);
                $checkOut = $assign->checked_out_at ? Carbon::parse($assign->checked_out_at) : Carbon::now();
                $start = $checkIn->max($from);
                $end = $checkOut->min($to);
                return max(0, $start->diffInDays($end));
            });

            return [
                'room' => $room->room_number,
                'type' => $room->roomType->name ?? 'N/A',
                'status' => $room->status,
                'total_stays' => $room->roomAssignments->count(),
                'days_occupied' => $daysOccupied,
                'utilization_rate' => round(($daysOccupied / $totalDays) * 100, 1),
            ];
        })->sortByDesc('utilization_rate')->values()->toArray();

        // By room type
        $byType = RoomType::withCount(['rooms' => function ($q) {
            $q->where('is_active', true);
        }])->get()->map(function ($type) use ($from, $to, $totalDays) {
            $stayCount = RoomAssignment::whereHas('room', function ($q) use ($type) {
                $q->where('room_type_id', $type->id);
            })->whereBetween('checked_in_at', [$from, $to])->count();

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
                    'checked_in' => $assign->checked_in_at ? Carbon::parse($assign->checked_in_at)->format('M d, Y h:i A') : '-',
                    'checked_out' => $assign->checked_out_at ? Carbon::parse($assign->checked_out_at)->format('M d, Y h:i A') : 'Still checked in',
                    'checked_in_by' => $assign->assignedByUser->name ?? '-',
                    'checked_out_by' => optional($assign->checkedOutByUser)->name ?? '-',
                    // Ensure nights is always an integer
                    'nights' => $assign->checked_out_at
                        ? Carbon::parse($assign->checked_in_at)->diffInDays(Carbon::parse($assign->checked_out_at))
                        : Carbon::parse($assign->checked_in_at)->diffInDays(Carbon::now()),
                    'remarks' => $assign->remarks ?? '-',
                ];
            })->toArray();

        return [
            'type' => 'stay_logs',
            'logs' => $logs,
            'total_stays' => count($logs),
            'completed' => collect($logs)->where('checked_out', '!=', 'Still checked in')->count(),
            'ongoing' => collect($logs)->where('checked_out', 'Still checked in')->count(),
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
                $assignedRooms = $reservation->roomAssignments->map(fn($assignment) => $assignment->room->room_number)->join(', ');
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