<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Room;
use App\Models\Service;
use App\Models\Guest;
use App\Models\RoomAssignment;

class RoomAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'roomAssignments';

    protected static ?string $title = 'Room Assignments';

    /**
     * Default form — used by EditAction.
     * For creating new assignments (checking in additional guests) see the
     * custom headerAction 'checkInAdditionalGuest' below.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('room_id')
                    ->label('Room')
                    ->options(function () {
                        return Room::query()
                            ->where('is_active', true)
                            ->orderBy('room_number')
                            ->pluck('room_number', 'id')
                            ->toArray();
                    })
                    ->searchable(),
                Forms\Components\Textarea::make('notes')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('guest_name')
                    ->label('Guest')
                    ->getStateUsing(fn ($record) => trim("{$record->guest_first_name} {$record->guest_last_name}"))
                    ->searchable(['guest_first_name', 'guest_last_name']),
                Tables\Columns\TextColumn::make('room.room_number')
                    ->label('Room')
                    ->badge()
                    ->color('info')
                    ->size('lg'),
                Tables\Columns\TextColumn::make('guest_gender')
                    ->label('Guest Gender')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '—'))
                    ->color(fn ($state) => match (strtolower($state)) {
                        'male'   => 'info',
                        'female' => 'danger',
                        default  => 'gray',
                    }),
                Tables\Columns\TextColumn::make('guest_age')
                    ->label('Age')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Official Check-in')
                    ->date('M d, Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('checked_out_at')
                    ->label('Actual Check-out')
                    ->date('M d, Y')
                    ->placeholder('—'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['reservation']))
            ->filters([])
            ->paginated(false)
            ->heading(null)
            ->description(function () {
                $reservation = $this->getOwnerRecord();
                $count       = $reservation->roomAssignments()->count();

                if ($count === 0) {
                    return 'No room assignments yet. Use the main Check In action on the reservation to start.';
                }

                $maleCount   = $reservation->roomAssignments()->where('guest_gender','Male')->count();
                $femaleCount = $reservation->roomAssignments()->where('guest_gender','Female')->count();
                $other       = $reservation->roomAssignments()->whereNotIn('guest_gender', ['Male', 'Female'])->count();

                $parts = array_filter([
                    $maleCount   ? "{$maleCount} male"   : '',
                    $femaleCount ? "{$femaleCount} female" : '',
                    $other       ? "{$other} other"        : '',
                ]);

                return "{$count} guest(s) checked in — " . implode(', ', $parts) . '.';
            })
            ->headerActions([
                // ── Add New Guest ────────────────────────────────────────────
                Tables\Actions\Action::make('addNewGuest')
                    ->label('➕ Add Guest')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn () => $this->getOwnerRecord()?->status === 'checked_in')
                    ->modalHeading('Add New Guest')
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\Select::make('room_id')
                            ->label('Add To Room')
                            ->options(function () {
                                $reservation = $this->getOwnerRecord();

                                return $reservation->roomAssignments()
                                    ->with('room.roomType')
                                    ->get()
                                    ->filter(fn ($assignment) => $assignment->room)
                                    ->unique('room_id')
                                    ->sortBy(fn ($assignment) => $assignment->room->room_number)
                                    ->mapWithKeys(function ($assignment) {
                                        $room = $assignment->room;
                                        $slots = $room->availableSlots();
                                        $suffix = " | {$slots} slot(s) free";

                                        return [
                                            $room->id => "Room {$room->room_number}{$suffix}",
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->default(fn () => $this->getOwnerRecord()->roomAssignments()->value('room_id'))
                            ->required()
                            ->searchable()
                            ->helperText('Choose which currently assigned room should receive this guest.'),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('middle_initial')
                            ->label('M.I.')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('age')
                            ->label('Age')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120),
                        Forms\Components\Select::make('gender')
                            ->label('Gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                                'Other' => 'Other',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Repeater::make('additional_requests')
                            ->label('Add-Ons')
                            ->schema([
                                Forms\Components\Select::make('code')
                                    ->label('Add-On')
                                    ->options(fn () => Service::active()->ordered()->get()
                                        ->mapWithKeys(fn (Service $service) => [
                                            $service->code => $service->name .
                                                ($service->price > 0 ? " ({$service->formatted_price})" : ' (Free)'),
                                        ])
                                    )
                                    ->required()
                                    ->searchable()
                                    ->distinct(),
                                Forms\Components\TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add Add-On')
                            ->columns(2)
                            ->helperText('Optional. Selected add-ons will be attached to this guest assignment.'),
                    ])
                    ->action(function ($livewire, array $data) {
                        $reservation = $this->getOwnerRecord();

                        if ($reservation->status !== 'checked_in') {
                            Notification::make()
                                ->danger()
                                ->title('Cannot Add Guest')
                                ->body('Guests can only be added while the reservation is currently checked in.')
                                ->send();

                            return;
                        }

                        $selectedRoomId = $data['room_id'] ?? null;

                        if (! $selectedRoomId) {
                            Notification::make()
                                ->danger()
                                ->title('No Room Available')
                                ->body('Please choose a room to add this guest into.')
                                ->send();

                            return;
                        }

                        $defaultRoom = Room::query()
                            ->with(['roomType'])
                            ->where('id', $selectedRoomId)
                            ->where('is_active', true)
                            ->first();

                        if (! $defaultRoom) {
                            Notification::make()
                                ->danger()
                                ->title('No Active Room Found')
                                ->body('The default assigned room is no longer active.')
                                ->send();

                            return;
                        }

                        // Capacity check — applies to all room types
                        if ($defaultRoom->isFull()) {
                            Notification::make()
                                ->danger()
                                ->title('Room Capacity Reached')
                                ->body("Room {$defaultRoom->room_number} has no available slots (capacity: {$defaultRoom->capacity}).")
                                ->send();

                            return;
                        }

                        $guest = $reservation->guests()->create([
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'middle_initial' => $data['middle_initial'] ?? null,
                            'gender' => $data['gender'],
                            'age' => $data['age'] ?? null,
                            'full_name' => trim(
                                ($data['first_name'] ?? '') . ' ' .
                                ($data['middle_initial'] ?? '') . ' ' .
                                ($data['last_name'] ?? '')
                            ),
                        ]);

                        $selectedServices = collect($data['additional_requests'] ?? [])
                            ->filter(fn ($i) => !empty($i['code'] ?? null))
                            ->values()
                            ->all();
                        $serviceAmount = null;
                        if (!empty($selectedServices)) {
                            $servicesMap = Service::whereIn('code', collect($selectedServices)->pluck('code')->unique())
                                ->get()->keyBy('code');
                            $calc = (float) collect($selectedServices)->sum(
                                fn ($i) => (float) ($servicesMap->get($i['code'])?->price ?? 0) * max(1, (int) ($i['qty'] ?? 1))
                            );
                            $serviceAmount = $calc > 0 ? $calc : null;
                        }

                        $reservation->roomAssignments()->create([
                            'guest_id' => $guest->id,
                            'room_id' => $defaultRoom->id,
                            'assigned_by' => auth()->id(),
                            'assigned_at' => now(),
                            'checked_in_at' => now(),
                            'checked_in_by' => auth()->id(),
                            'status' => 'checked_in',
                            'guest_last_name' => $data['last_name'],
                            'guest_first_name' => $data['first_name'],
                            'guest_middle_initial' => $data['middle_initial'] ?? null,
                            'guest_gender' => $data['gender'],
                            'guest_age' => $data['age'] ?? null,
                            'additional_requests' => empty($selectedServices) ? null : $selectedServices,
                            'payment_amount' => $serviceAmount,
                        ]);

                        $this->syncReservationOccupancyCounts($reservation);

                        Notification::make()
                            ->success()
                            ->title('Guest Added')
                            ->body("{$guest->full_name} has been added to this reservation.")
                            ->send();

                        $livewire->dispatch('$refresh');
                    }),

                // ── Check In Additional Guest(s) ────────────────────────────
                Tables\Actions\Action::make('checkInAdditionalGuest')
                    ->label('Check In Guest(s)')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->modalHeading('Check In Additional Guest(s)')
                    ->modalWidth('5xl')
                    ->visible(false) // Consolidated into primary "Check In Reservation" action for consistency
                    ->form(function () {
                        $reservation = $this->getOwnerRecord();
                        return [
                            // ── Step 1: Room ─────────────────────────────────────────
                            Forms\Components\Section::make('Room & Bed Assignment')
                                ->description('First select a room, then choose one bed per guest.')
                                ->schema([
                                    Forms\Components\Select::make('room_id')
                                        ->label('Select Room')
                                        ->default($reservation->roomAssignments()->first()?->room_id)
                                    ->options(function ($get) use ($reservation) {
                                            $query = Room::query()
                                                ->where('is_active', true)
                                                ->where('room_type_id', $reservation->preferred_room_type_id)
                                                ->where('status', 'available');

                                            return $query->get()->mapWithKeys(fn ($room) => [
                                            $room->id => "Room {$room->room_number} — {$room->availableSlots()} slot(s) free",
                                            ]);
                                        })
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function ($set) {
                                            // room changed — nothing to reset
                                        })
                                        ->helperText('Rooms are filtered by the reservation\'s room type.'),

                                    Forms\Components\Placeholder::make('room_occupancy_info')
                                        ->label('Room Occupancy')
                                        ->content(function ($get) {
                                            $roomId = $get('room_id');
                                            if (! $roomId) {
                                                return '— Select a room above to see occupancy.';
                                            }
                                            $room = Room::find($roomId);
                                            if (! $room) {
                                                return 'Room not found.';
                                            }
                                            $occupied  = $room->currentOccupancy();
                                            $available = $room->availableSlots();
                                            return "Room {$room->room_number} — "
                                                 . "{$occupied}/{$room->capacity} slots occupied, {$available} available.";
                                        }),
                                ])->columns(2),

                            // ── Step 2: Guest Names ──────────────────────────────────
                            Forms\Components\Section::make('Guest Names')
                                ->description('Add one row per selected bed. Each row maps to the next bed in selection order. ⚠️ **Mixed-gender groups:** Check in each gender separately to appropriate rooms.')
                                ->schema([
                                    Forms\Components\Repeater::make('guests')
                                        ->label(false)
                                        ->schema([
                                            Forms\Components\TextInput::make('last_name')
                                                ->label('Last Name')
                                                ->required()
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('first_name')
                                                ->label('First Name')
                                                ->required()
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('middle_initial')
                                                ->label('M.I.')
                                                ->maxLength(10),
                                            Forms\Components\TextInput::make('age')
                                                ->label('Age')
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(120),
                                            Forms\Components\Select::make('gender')
                                                ->label('Gender')
                                                ->required()
                                                ->options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'])
                                                ->native(false),
                                        ])
                                        ->columns(4)
                                        ->defaultItems(1)
                                        ->addActionLabel('+ Add Guest')
                                        ->reorderable(false)
                                        ->itemLabel(fn (array $state): ?string =>
                                            ($state['first_name'] ?? '') || ($state['last_name'] ?? '')
                                                ? trim(($state['first_name'] ?? '') . ' ' . ($state['last_name'] ?? ''))
                                                : 'Guest'
                                        )
                                        ->columnSpanFull(),
                                ]),

                            // ── Step 3: Schedule ─────────────────────────────────────
                            Forms\Components\Section::make('Check-in / Check-out')
                                ->schema([
                                    Forms\Components\DatePicker::make('detailed_checkin_datetime')
                                        ->label('Check-in Date')
                                        ->default($reservation->check_in_date)
                                        ->required()->native(false),
                                    Forms\Components\DatePicker::make('detailed_checkout_datetime')
                                        ->label('Check-out Date')
                                        ->default($reservation->check_out_date)
                                        ->required()->native(false)
                                        ->after('detailed_checkin_datetime'),
                                ])->columns(2),
                        ];
                    })
                    ->modalFooterActions([
                        Tables\Actions\Action::make('addGuest')
                            ->label('Add New Guest')
                            ->icon('heroicon-o-plus')
                            ->color('success')
                            ->form([
                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('middle_initial')
                                    ->label('M.I.')
                                    ->maxLength(10),
                                Forms\Components\TextInput::make('age')
                                    ->label('Age')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(120),
                                Forms\Components\Select::make('gender')
                                    ->label('Gender')
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                        'Other' => 'Other',
                                    ])
                                    ->required(),
                            ])
                            ->action(function ($livewire, array $data) {
                                $reservation = $livewire->getOwnerRecord();

                                $data['full_name'] = trim(
                                    ($data['first_name'] ?? '') . ' ' .
                                    ($data['middle_initial'] ?? '') . ' ' .
                                    ($data['last_name'] ?? '')
                                );

                                $reservation->guests()->create($data);

                                Notification::make()
                                    ->success()
                                    ->title('Guest Added')
                                    ->body('Guest has been added to the list.')
                                    ->send();
                            }),
                        Tables\Actions\Action::make('close')
                            ->label('Close')
                            ->color('gray')
                            ->cancelParentActions(),
                    ])
                    ->action(function ($livewire, array $data) {
                        $reservation = $livewire->getOwnerRecord();
                        $guests      = array_values($data['guests'] ?? []);
                        $roomId      = $data['room_id'] ?? null;

                        if (empty($guests) || ! $roomId) {
                            Notification::make()->danger()->title('Invalid Data')->body('A room and at least one guest are required.')->send();
                            return;
                        }

                        $room = Room::with('roomType')->find($roomId);
                        if (! $room) {
                            Notification::make()->danger()->title('Room Not Found')->body('The selected room could not be found.')->send();
                            return;
                        }

                        $checkedIn = 0;
                        foreach ($guests as $guest) {
                            if (empty($guest['first_name'] ?? null)) {
                                continue;
                            }

                            if ($room->isFull()) {
                                Notification::make()->warning()->title('Room Full')
                                    ->body("Room {$room->room_number} is at capacity. Some guests were not checked in.")
                                    ->send();
                                break;
                            }

                            $fullName = trim(
                                ($guest['first_name'] ?? '') . ' ' .
                                (($guest['middle_initial'] ?? '') ? $guest['middle_initial'] . ' ' : '') .
                                ($guest['last_name'] ?? '')
                            );

                            $guestRecord = Guest::firstOrCreate([
                                'reservation_id' => $reservation->id,
                                'first_name'     => $guest['first_name'] ?? null,
                                'last_name'      => $guest['last_name'] ?? null,
                                'middle_initial' => $guest['middle_initial'] ?? null,
                                'gender'         => $guest['gender'] ?? null,
                            ], [
                                'full_name' => $fullName ?: 'Unknown',
                                'age'       => $guest['age'] ?? null,
                            ]);

                            RoomAssignment::create([
                                'reservation_id'             => $reservation->id,
                                'guest_id'                   => $guestRecord->id,
                                'room_id'                    => $room->id,
                                'status'                     => 'checked_in',
                                'assigned_by'                => auth()->id(),
                                'assigned_at'                => now(),
                                'checked_in_at'              => now(),
                                'checked_in_by'              => auth()->id(),
                                'guest_last_name'            => $guest['last_name'] ?? null,
                                'guest_first_name'           => $guest['first_name'] ?? null,
                                'guest_middle_initial'       => $guest['middle_initial'] ?? null,
                                'guest_gender'               => $guest['gender'] ?? null,
                                'guest_age'                  => $guest['age'] ?? null,
                                'nationality'                => 'Filipino',
                                'purpose_of_stay'            => $reservation->purpose ?? null,
                                'detailed_checkin_datetime'  => $data['detailed_checkin_datetime'],
                                'detailed_checkout_datetime' => $data['detailed_checkout_datetime'],
                                'num_male_guests'            => 0,
                                'num_female_guests'          => 0,
                            ]);

                            $checkedIn++;
                        }

                        Notification::make()->success()->title('Guest(s) Checked In')
                            ->body("Successfully checked in {$checkedIn} guest(s).")
                            ->send();

                        $this->syncReservationOccupancyCounts($reservation);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->modalHeading('Edit Guest Assignment')
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\Section::make('Guest Assignment')
                            ->schema([
                                Forms\Components\Select::make('room_id')
                                    ->label('Room')
                                    ->options(function () {
                                        return Room::query()
                                            ->where('is_active', true)
                                            ->orderBy('room_number')
                                            ->pluck('room_number', 'id')
                                            ->toArray();
                                    })
                                    ->required()
                                    ->searchable(),
                                Forms\Components\TextInput::make('guest_last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('guest_first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('guest_middle_initial')
                                    ->label('Middle Initial')
                                    ->maxLength(10),
                                Forms\Components\Select::make('guest_gender')
                                    ->label('Guest Gender')
                                    ->required()
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                        'Other' => 'Other',
                                    ])
                                    ->native(false),
                            ])->columns(2),
                    ])
                    ->after(function (RoomAssignment $record) {
                        $reservation = $this->getOwnerRecord();
                        $this->syncReservationOccupancyCounts($reservation);
                    })
                    ->successNotificationTitle('Room assignment updated successfully'),
                Tables\Actions\Action::make('unassignGuest')
                    ->label('Unassign')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Unassign Guest')
                    ->modalDescription('Are you sure you want to remove this guest assignment from this reservation?')
                    ->action(function (RoomAssignment $record) {
                        $guest = $record->guest;
                        $reservationId = $record->reservation_id;

                        $record->delete();

                        // If this guest no longer has assignments in this reservation,
                        // remove the guest row as well to keep the reservation list consistent.
                        if ($guest && ! $guest->roomAssignments()->where('reservation_id', $reservationId)->exists()) {
                            $guest->delete();
                        }

                        $reservation = $this->getOwnerRecord();
                        $this->syncReservationOccupancyCounts($reservation);

                        Notification::make()
                            ->success()
                            ->title('Guest Unassigned')
                            ->body('Guest assignment was removed successfully.')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Room Assignments Yet')
            ->emptyStateDescription('Use the "Check In" action on the reservation to assign a room to guests.')
            ->emptyStateIcon('heroicon-o-home')
            ->emptyStateActions([]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Room Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('room.room_number')
                            ->label('Room Number'),
                        Infolists\Components\TextEntry::make('room.roomType.name')
                            ->label('Room Type'),
                        Infolists\Components\TextEntry::make('room.floor.name')
                            ->label('Floor'),
                    ])->columns(5),
                
                Infolists\Components\Section::make('Guest Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('guest_first_name')
                            ->label('First Name'),
                        Infolists\Components\TextEntry::make('guest_middle_initial')
                            ->label('Middle Initial'),
                        Infolists\Components\TextEntry::make('guest_last_name')
                            ->label('Last Name'),
                        Infolists\Components\TextEntry::make('guest_full_address')
                            ->label('Complete Address')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('guest_contact_number')
                            ->label('Contact Number'),
                        Infolists\Components\TextEntry::make('reservation.guest_gender')
                            ->label('Gender'),
                        Infolists\Components\TextEntry::make('guest_age')
                            ->label('Age')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('nationality')
                            ->label('Nationality'),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Identification & Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('id_type')
                            ->label('ID Type')
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state ?? ''))),
                        Infolists\Components\TextEntry::make('id_number')
                            ->label('ID Number'),
                        Infolists\Components\IconEntry::make('is_student')
                            ->label('Student')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('is_senior_citizen')
                            ->label('Senior Citizen')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('is_pwd')
                            ->label('PWD')
                            ->boolean(),
                    ])->columns(5),
                
                Infolists\Components\Section::make('Stay Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('purpose_of_stay')
                            ->label('Purpose of Stay'),
                        Infolists\Components\TextEntry::make('detailed_checkin_datetime')
                            ->label('Date of Arrival')
                            ->date(),
                        Infolists\Components\TextEntry::make('detailed_checkout_datetime')
                            ->label('Scheduled Check-out')
                            ->date(),
                        Infolists\Components\TextEntry::make('checked_in_at')
                            ->label('Official Check-in (Payment)')
                            ->date('M d, Y')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('checked_out_at')
                            ->label('Actual Check-out')
                            ->date('M d, Y')
                            ->placeholder('—'),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Add-Ons & Payment')
                    ->schema([
                        Infolists\Components\TextEntry::make('additional_requests')
                            ->label('Add-Ons')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'None requested';
                                }
                                $items = collect($state);
                                // Detect legacy format (array of plain code strings)
                                if ($items->first() !== null && is_string($items->first())) {
                                    $serviceNames = $items->map(function ($code) {
                                        $service = Service::where('code', $code)->first();
                                        return $service
                                            ? $service->name . ($service->price > 0 ? " ({$service->formatted_price})" : ' (Free)')
                                            : $code;
                                    })->filter();
                                } else {
                                    $serviceNames = $items->filter(fn ($i) => !empty($i['code'] ?? null))->map(function ($item) {
                                        $qty = max(1, (int) ($item['qty'] ?? 1));
                                        $service = Service::where('code', $item['code'])->first();
                                        if ($service) {
                                            $lineTotal = (float) $service->price * $qty;
                                            $label = $service->name . ($service->price > 0 ? " (₱" . number_format($lineTotal, 2) . ")" : ' (Free)');
                                            return $qty > 1 ? "{$qty}x {$label}" : $label;
                                        }
                                        return $item['code'];
                                    })->filter();
                                }
                                return $serviceNames->isEmpty() ? 'None requested' : $serviceNames->implode(', ');
                            })
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('payment_mode')
                            ->label('Payment Mode')
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state ?? ''))),
                        Infolists\Components\TextEntry::make('payment_amount')
                            ->label('Payment Amount')
                            ->money('PHP'),
                        Infolists\Components\TextEntry::make('payment_or_number')
                            ->label('OR Number'),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Assignment Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('assignedByUser.name')
                            ->label('Assigned By'),
                        Infolists\Components\TextEntry::make('assigned_at')
                            ->label('Assigned At')
                            ->date(),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Guest List')
                    ->schema([
                        Infolists\Components\ViewEntry::make('reservation.guests')
                            ->label('')
                            ->view('filament.infolists.guest-list-table')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->description(function ($record) {
                        $reservation = $record->reservation;
                        $guestCount = $reservation->guests()->count();
                        $maleCount = $reservation->guests()->where('gender', 'Male')->count();
                        $femaleCount = $reservation->guests()->where('gender', 'Female')->count();
                        return "{$guestCount} total guests ({$maleCount} male, {$femaleCount} female)";
                    }),
            ]);
    }

    public int $roomAssignmentsRefreshCounter = 0;

    protected function getListeners(): array
    {
        return array_merge(parent::getListeners(), [
            'roomAssigned' => 'handleRoomAssigned',
        ]);
    }

    public function handleRoomAssigned(): void
    {
        $this->roomAssignmentsRefreshCounter++;
    }

    private function syncReservationOccupancyCounts($reservation): void
    {
        $activeAssignments = $reservation->roomAssignments()
            ->where('status', 'checked_in');

        $total = (clone $activeAssignments)->count();
        $male = (clone $activeAssignments)->where('guest_gender', 'Male')->count();
        $female = (clone $activeAssignments)->where('guest_gender', 'Female')->count();

        $reservation->update([
            'number_of_occupants' => $total,
            'num_male_guests' => $male,
            'num_female_guests' => $female,
        ]);
    }

}
