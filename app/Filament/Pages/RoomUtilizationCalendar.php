<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use App\Services\RoomUtilizationCalendarService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;

class RoomUtilizationCalendar extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?string $navigationLabel = 'Room Utilization Calendar';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.room-utilization-calendar';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $floorId = null;

    public ?string $roomTypeId = null;

    public ?string $roomStatus = null;

    /**
     * @var array<int,string>
     */
    public array $visibleTypes = ['holds', 'assignments', 'room_states', 'unassigned'];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission(User::REPORT_ROOM_UTILIZATION_VIEW) ?? false;
    }

    public function mount(): void
    {
        $this->dateFrom = Carbon::today()->toDateString();
        $this->dateTo = Carbon::today()->addDays(14)->toDateString();
    }

    public function getTitle(): string
    {
        return 'Room Utilization Calendar';
    }

    public function updatedDateFrom(): void
    {
        $this->normalizeDateRange();
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->normalizeDateRange();
        $this->resetPage();
    }

    public function previousRange(): void
    {
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);
        $days = max(1, $from->diffInDays($to) + 1);

        $this->dateFrom = $from->subDays($days)->toDateString();
        $this->dateTo = $to->subDays($days)->toDateString();
        $this->resetPage();
    }

    public function nextRange(): void
    {
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);
        $days = max(1, $from->diffInDays($to) + 1);

        $this->dateFrom = $from->addDays($days)->toDateString();
        $this->dateTo = $to->addDays($days)->toDateString();
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = Carbon::today()->toDateString();
        $this->dateTo = Carbon::today()->addDays(14)->toDateString();
        $this->floorId = null;
        $this->roomTypeId = null;
        $this->roomStatus = null;
        $this->visibleTypes = ['holds', 'assignments', 'room_states', 'unassigned'];
        $this->resetPage();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getUnassignedReservationsQuery())
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Reservation $record): string => ReservationResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('guest_name')
                    ->label('Guest')
                    ->searchable(['guest_first_name', 'guest_last_name', 'guest_name'])
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('requested_room_summary')
                    ->label('Requested Rooms')
                    ->getStateUsing(fn (Reservation $record): string => $record->requested_room_summary)
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $nested) use ($search): void {
                            $nested
                                ->whereHas('preferredRoomType', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('roomRequests.roomType', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', "%{$search}%"));
                        });
                    }),
                Tables\Columns\TextColumn::make('check_in_date')
                    ->label('Check In')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out_date')
                    ->label('Check Out')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('number_of_occupants')
                    ->label('Guests')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'confirmed' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('guest_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('guest_phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'confirmed' => 'Confirmed',
                    ]),
                Tables\Filters\SelectFilter::make('requested_room_type_id')
                    ->label('Room Type')
                    ->options(fn (): array => $this->roomTypeOptions)
                    ->query(function (Builder $query, array $data): Builder {
                        $roomTypeId = $data['value'] ?? null;

                        return $query->when($roomTypeId, function (Builder $query) use ($roomTypeId): Builder {
                            return $query->where(function (Builder $nested) use ($roomTypeId): void {
                                $nested
                                    ->where('preferred_room_type_id', $roomTypeId)
                                    ->orWhereHas('roomRequests', fn (Builder $requestQuery) => $requestQuery->where('room_type_id', $roomTypeId));
                            });
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Reservation $record): string => ReservationResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Reservation $record): string => ReservationResource::getUrl('view', ['record' => $record]))
            ->defaultSort('check_in_date')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No unassigned requests')
            ->emptyStateDescription('Reservations without held or assigned rooms for the selected date range will appear here.')
            ->emptyStateIcon('heroicon-o-calendar');
    }

    public function getCalendarDataProperty(): array
    {
        return app(RoomUtilizationCalendarService::class)->build([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'floor_id' => $this->floorId,
            'room_type_id' => $this->roomTypeId,
            'room_status' => $this->roomStatus,
            'visible_types' => $this->visibleTypes,
        ]);
    }

    public function getFloorOptionsProperty(): array
    {
        return Floor::query()
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getRoomTypeOptionsProperty(): array
    {
        return RoomType::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function getUnassignedReservationsQuery(): Builder
    {
        [$from, $to] = $this->calendarRange();

        return app(RoomUtilizationCalendarService::class)
            ->queryUnassignedReservations($from, $to)
            ->with(['preferredRoomType', 'roomRequests.roomType'])
            ->orderBy('check_in_date')
            ->orderBy('reference_number');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function calendarRange(): array
    {
        $from = $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfDay() : Carbon::today()->startOfDay();
        $to = $this->dateTo ? Carbon::parse($this->dateTo)->startOfDay() : $from->copy()->addDays(14);

        if ($to->lessThan($from)) {
            $to = $from->copy();
        }

        if ($from->diffInDays($to) > 30) {
            $to = $from->copy()->addDays(30);
        }

        return [$from, $to];
    }

    protected function normalizeDateRange(): void
    {
        if (! $this->dateFrom || ! $this->dateTo) {
            return;
        }

        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        if ($to->lessThan($from)) {
            $this->dateTo = $from->toDateString();

            return;
        }

        if ($from->diffInDays($to) > 30) {
            $this->dateTo = $from->copy()->addDays(30)->toDateString();
        }
    }
}
