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
use App\Models\Bed;
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
                Forms\Components\Select::make('bed_id')
                    ->label('Bed')
                    ->options(function () {
                        return Bed::query()
                            ->whereHas('room', fn ($q) => $q->where('is_active', true))
                            ->with('room')
                            ->get()
                            ->mapWithKeys(fn ($bed) => [
                                $bed->id => "Room {$bed->room->room_number} — {$bed->bed_number} ({$bed->status})",
                            ]);
                    })
                    ->searchable()
                    ->nullable()
                    ->helperText('Optional: leave blank for room-level assignment only.'),
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
                Tables\Columns\TextColumn::make('bed.bed_number')
                    ->label('Bed')
                    ->badge()
                    ->color('success')
                    ->default('—'),
                Tables\Columns\TextColumn::make('guest_gender')
                    ->label('Guest Gender')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '—'))
                    ->color(fn ($state) => match (strtolower($state)) {
                        'male'   => 'info',
                        'female' => 'danger',
                        default  => 'gray',
                    }),
                Tables\Columns\TextColumn::make('additional_requests')
                    ->label('Services')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'None';
                        }
                        $serviceNames = collect($state)->map(function ($code) {
                            return \App\Models\Service::where('code', $code)->first()?->name ?? $code;
                        })->filter();
                        return $serviceNames->isEmpty() ? 'None' : $serviceNames->implode(', ');
                    })
                    ->color(fn ($state) => empty($state) ? 'gray' : 'success')
                    ->wrap(),
                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('Payment')
                    ->money('PHP')
                    ->default('—'),
                Tables\Columns\TextColumn::make('assignedByUser.name')
                    ->label('Checked In By'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['reservation', 'bed']))
            ->filters([])
            ->paginated(false)
            ->heading(null)
            ->description(function () {
                $reservation = $this->getOwnerRecord();
                $count       = $reservation->roomAssignments()->count();

                if ($count === 0) {
                    return ($reservation?->preferredRoomType?->isPrivate() ?? false)
                        ? 'No room assignments yet. Use the main Check In action on the reservation to start.'
                        : 'No bed assignments yet. Use the main Check In action on the reservation to start.';
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
                                        $suffix = $room->roomType?->isPublic()
                                            ? ' | ' . $room->availableBedsCount() . ' bed(s) free'
                                            : '';

                                        return [
                                            $room->id => "Room {$room->room_number} ({$room->getGenderLabel()}){$suffix}",
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
                        Forms\Components\Select::make('gender')
                            ->label('Gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                                'Other' => 'Other',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\CheckboxList::make('additional_requests')
                            ->label('Additional Services')
                            ->options(function () {
                                return Service::active()
                                    ->ordered()
                                    ->get()
                                    ->mapWithKeys(fn (Service $service) => [
                                        $service->code => $service->name .
                                            ($service->price > 0 ? " ({$service->formatted_price})" : ' (Free)'),
                                    ]);
                            })
                            ->columns(2)
                            ->helperText('Optional. Selected services will be attached to this guest assignment.'),
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
                            ->with(['roomType', 'beds'])
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

                        $guestGender = strtolower((string) ($data['gender'] ?? ''));
                        if ($defaultRoom->gender_type !== 'any' && $guestGender !== $defaultRoom->gender_type) {
                            Notification::make()
                                ->danger()
                                ->title('Gender Mismatch')
                                ->body("Room {$defaultRoom->room_number} only accepts {$defaultRoom->getGenderLabel()} guests.")
                                ->send();

                            return;
                        }

                        $assignedBedId = null;
                        if ($defaultRoom->roomType?->isPublic()) {
                            $availableBed = Bed::query()
                                ->where('room_id', $defaultRoom->id)
                                ->where('status', 'available')
                                ->orderBy('bed_number')
                                ->first();

                            if (! $availableBed) {
                                Notification::make()
                                    ->danger()
                                    ->title('No Available Bed')
                                    ->body("Room {$defaultRoom->room_number} has no available beds.")
                                    ->send();

                                return;
                            }

                            $assignedBedId = $availableBed->id;
                        } else {
                            $activeInRoom = RoomAssignment::query()
                                ->where('reservation_id', $reservation->id)
                                ->where('room_id', $defaultRoom->id)
                                ->where('status', 'checked_in')
                                ->count();

                            if ($activeInRoom >= (int) $defaultRoom->capacity) {
                                Notification::make()
                                    ->danger()
                                    ->title('Room Capacity Reached')
                                    ->body("Room {$defaultRoom->room_number} already reached its capacity of {$defaultRoom->capacity} guest(s).")
                                    ->send();

                                return;
                            }
                        }

                        $guest = $reservation->guests()->create([
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'middle_initial' => $data['middle_initial'] ?? null,
                            'gender' => $data['gender'],
                            'full_name' => trim(
                                ($data['first_name'] ?? '') . ' ' .
                                ($data['middle_initial'] ?? '') . ' ' .
                                ($data['last_name'] ?? '')
                            ),
                        ]);

                        $selectedServices = $data['additional_requests'] ?? [];
                        $serviceAmount = empty($selectedServices)
                            ? null
                            : (float) Service::whereIn('code', $selectedServices)->sum('price');

                        $reservation->roomAssignments()->create([
                            'guest_id' => $guest->id,
                            'room_id' => $defaultRoom->id,
                            'bed_id' => $assignedBedId,
                            'assigned_by' => auth()->id(),
                            'assigned_at' => now(),
                            'checked_in_at' => now(),
                            'checked_in_by' => auth()->id(),
                            'status' => 'checked_in',
                            'guest_last_name' => $data['last_name'],
                            'guest_first_name' => $data['first_name'],
                            'guest_middle_initial' => $data['middle_initial'] ?? null,
                            'guest_gender' => $data['gender'],
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
                                            // Determine genders from modal guest rows first
                                            $modalGuests = $get('guests') ?? [];
                                            $genders = collect($modalGuests)
                                                ->pluck('gender')
                                                ->filter()
                                                ->map(fn ($g) => strtolower($g))
                                                ->unique()
                                                ->values()
                                                ->all();

                                            // Fallback to reservation's persisted guests if none entered in modal
                                            if (empty($genders)) {
                                                $genders = $reservation->guests()->pluck('gender')->map(fn ($g) => strtolower($g))->unique()->values()->all();
                                            }

                                            // Map to allowed room gender types (only male/female map; others default to no-filter)
                                            $allowedRoomGenders = array_values(array_filter(array_map(fn ($g) => in_array($g, ['male', 'female']) ? $g : null, $genders)));

                                            $query = Room::query()
                                                ->where('is_active', true)
                                                ->where('room_type_id', $reservation->preferred_room_type_id);

                                            // ✅ For dormitory (public) rooms: show rooms with available beds
                                            // For private rooms: show only available rooms
                                            $roomType = $reservation->preferredRoomType;
                                            if ($roomType && $roomType->room_sharing_type === 'public') {
                                                // Dormitory: include rooms that have available beds
                                                $query->whereHas('beds', function ($q) {
                                                    $q->where('status', 'available');
                                                });
                                            } else {
                                                // Private rooms: must be available status
                                                $query->where('status', 'available');
                                            }

                                            if (! empty($allowedRoomGenders)) {
                                                $query->where(function ($q) use ($allowedRoomGenders) {
                                                    $q->where('gender_type', 'any')
                                                      ->orWhereIn('gender_type', $allowedRoomGenders);
                                                });
                                            }

                                            return $query->get()->mapWithKeys(fn ($room) => [
                                                $room->id => "Room {$room->room_number} ({$room->getGenderLabel()}) — {$room->availableBedsCount()} bed(s) free",
                                            ]);
                                        })
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function ($set) {
                                            $set('bed_ids', []);
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
                                            $total     = $room->beds()->count();
                                            $available = $room->availableBedsCount();
                                            return "Room {$room->room_number} ({$room->getGenderLabel()}) — "
                                                 . "{$occupied}/{$total} beds occupied, {$available} available.";
                                        }),

                                    Forms\Components\CheckboxList::make('bed_ids')
                                        ->label('Select Beds')
                                        ->options(function ($get) {
                                            $roomId = $get('room_id');
                                            if (! $roomId) {
                                                return [];
                                            }
                                            return Bed::query()
                                                ->where('room_id', $roomId)
                                                ->where('status', 'available')
                                                ->get()
                                                ->sortBy('bed_number', SORT_NATURAL)
                                                ->mapWithKeys(fn ($bed) => [(string) $bed->id => $bed->bed_number]);
                                        })
                                        ->required()
                                        ->helperText('Select one bed per guest. Add a guest row below for each selected bed.')
                                        ->columns(5)
                                        ->columnSpanFull(),
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
                                    Forms\Components\DateTimePicker::make('detailed_checkin_datetime')
                                        ->label('Check-in Date & Time')
                                        ->default($reservation->check_in_date)
                                        ->required()->native(false)->seconds(false),
                                    Forms\Components\DateTimePicker::make('detailed_checkout_datetime')
                                        ->label('Check-out Date & Time')
                                        ->default($reservation->check_out_date)
                                        ->required()->native(false)->seconds(false)
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
                        $reservation    = $livewire->getOwnerRecord();
                        $selectedBedIds = array_values($data['bed_ids'] ?? []);
                        $guests         = array_values($data['guests'] ?? []);

                        if (empty($selectedBedIds)) {
                            Notification::make()->danger()->title('No Beds Selected')
                                ->body('Please select at least one bed.')->send();
                            return;
                        }

                        // ✅ NEW: Validate that total assignments won't exceed number of occupants
                        $currentAssignments = $reservation->roomAssignments()->count();
                        $newGuestCount = count(array_filter($guests, fn($g) => !empty($g['first_name'] ?? null)));
                        $totalAfter = $currentAssignments + $newGuestCount;

                        if ($totalAfter > $reservation->number_of_occupants) {
                            $canAdd = $reservation->number_of_occupants - $currentAssignments;
                            Notification::make()
                                ->danger()
                                ->title('Exceeds Occupancy Limit')
                                ->body("This reservation has {$reservation->number_of_occupants} occupant(s). Currently {$currentAssignments} assigned. " .
                                       "You can only add {$canAdd} more guest(s), but you're trying to add {$newGuestCount}.")
                                ->persistent()
                                ->send();
                            return;
                        }

                        // ── Validate gender compatibility ─────────────────────────────
                        $errors = [];
                        foreach ($selectedBedIds as $index => $bedId) {
                            $bed   = \App\Models\Bed::with('room')->find($bedId);
                            $guest = $guests[$index] ?? null;

                            if (! $bed || ! $bed->room) continue;
                            if (! $guest || ! isset($guest['gender'])) continue;

                            $room        = $bed->room;
                            $guestGender = strtolower($guest['gender'] ?? '');
                            $roomGender  = $room->gender_type;

                            // Allow if room is 'any' or gender matches
                            if ($roomGender === 'any') continue;
                            if ($roomGender === 'male' && $guestGender === 'male') continue;
                            if ($roomGender === 'female' && $guestGender === 'female') continue;

                            // Mismatch detected
                            $guestName = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                            $roomLabel = $roomGender === 'male' ? 'Male-only' : ($roomGender === 'female' ? 'Female-only' : ucfirst($roomGender));
                            $errors[]  = "Cannot assign {$guest['gender']} guest '{$guestName}' to {$roomLabel} room {$room->room_number}.";
                        }

                        if (! empty($errors)) {
                            Notification::make()
                                ->danger()
                                ->title('Gender Mismatch Error')
                                ->body(implode(' ', $errors) . ' Please check in each gender separately to appropriate rooms.')
                                ->persistent()
                                ->send();
                            return;
                        }

                        $checkedIn = 0;
                        $skipped   = [];

                        foreach ($selectedBedIds as $index => $bedId) {
                            $bed    = Bed::with('room')->find($bedId);
                            $guest  = $guests[$index] ?? null;

                            if (! $bed || ! $bed->isAvailable()) {
                                $bedNum    = $index + 1;
                                $skipped[] = $bed?->bed_number ?? "Bed #{$bedNum}";
                                continue;
                            }

                            $roomId = $bed->room_id;

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
                                'full_name'      => $fullName ?: 'Unknown',
                            ]);

                            RoomAssignment::create([
                                'reservation_id'             => $reservation->id,
                                'guest_id'                   => $guestRecord->id,
                                'room_id'                    => $roomId,
                                'bed_id'                     => $bedId,
                                'status'                     => 'checked_in',
                                'assigned_by'                => auth()->id(),
                                'assigned_at'                => now(),
                                'checked_in_at'              => now(),
                                'checked_in_by'              => auth()->id(),
                                'guest_last_name'            => $guest['last_name'] ?? null,
                                'guest_first_name'           => $guest['first_name'] ?? null,
                                'guest_middle_initial'       => $guest['middle_initial'] ?? null,
                                'guest_gender'               => $guest['gender'] ?? null,
                                'nationality'                => 'Filipino',
                                'purpose_of_stay'            => $reservation->purpose ?? null,
                                'detailed_checkin_datetime'  => $data['detailed_checkin_datetime'],
                                'detailed_checkout_datetime' => $data['detailed_checkout_datetime'],
                                'num_male_guests'            => 0,
                                'num_female_guests'          => 0,
                            ]);

                            $bed->occupy();
                            $room = $bed->room;
                            if ($room) {
                                $occupiedBeds = $room->beds()->where('status', 'occupied')->count();
                                $room->update(['status' => $occupiedBeds > 0 ? 'occupied' : 'available']);
                            }
                            $checkedIn++;
                        }

                        if (! empty($skipped)) {
                            Notification::make()->warning()->title('Some Beds Unavailable')
                                ->body("Checked in {$checkedIn} guest(s). Skipped unavailable beds: " . implode(', ', $skipped))
                                ->send();
                        } else {
                            Notification::make()->success()->title('Guest(s) Checked In')
                                ->body("Successfully checked in {$checkedIn} guest(s).")
                                ->send();
                        }

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
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn ($set) => $set('bed_id', null)),
                                Forms\Components\Select::make('bed_id')
                                    ->label('Bed')
                                    ->options(function ($get) {
                                        $roomId = $get('room_id');
                                        if (! $roomId) {
                                            return [];
                                        }

                                        $selectedBedId = $get('bed_id');

                                        return Bed::query()
                                            ->where('room_id', $roomId)
                                            ->where(function ($query) use ($selectedBedId) {
                                                $query->where('status', 'available');

                                                if ($selectedBedId) {
                                                    $query->orWhere('id', $selectedBedId);
                                                }
                                            })
                                            ->orderBy('bed_number')
                                            ->get()
                                            ->mapWithKeys(fn ($bed) => [
                                                $bed->id => "{$bed->bed_number} ({$bed->status})",
                                            ]);
                                    })
                                    ->searchable()
                                    ->nullable()
                                    ->helperText('Optional for private room-level assignments.'),
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
                                Forms\Components\CheckboxList::make('additional_requests')
                                    ->label('Additional Services')
                                    ->options(function () {
                                        return Service::active()
                                            ->ordered()
                                            ->get()
                                            ->mapWithKeys(fn (Service $service) => [
                                                $service->code => $service->name .
                                                    ($service->price > 0 ? " ({$service->formatted_price})" : ' (Free)'),
                                            ]);
                                    })
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])
                    ->using(function (RoomAssignment $record, array $data) {
                        // Recalculate payment_amount based on selected services
                        $selectedServices = $data['additional_requests'] ?? [];
                        $serviceAmount = empty($selectedServices)
                            ? null
                            : (float) Service::whereIn('code', $selectedServices)->sum('price');

                        $data['payment_amount'] = $serviceAmount;

                        $record->update($data);
                        return $record;
                    })
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
            ->emptyStateHeading(fn () => ($this->getOwnerRecord()?->preferredRoomType?->isPrivate() ?? false) ? 'No Room Assignments Yet' : 'No Bed Assignments Yet')
            ->emptyStateDescription(fn () => ($this->getOwnerRecord()?->preferredRoomType?->isPrivate() ?? false)
                ? 'Use the "Check In" action on the reservation to assign a private room to guests.'
                : 'Use the "Check In" action on the reservation to assign beds to guests.')
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
                        Infolists\Components\TextEntry::make('bed.bed_number')
                            ->label('Bed')
                            ->visible(fn ($record) => ! ($record?->room?->roomType?->isPrivate() ?? false))
                            ->default('—'),
                        Infolists\Components\TextEntry::make('room.gender_type')
                            ->label('Room Gender')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'male'   => 'Male',
                                'female' => 'Female',
                                default  => 'Any',
                            }),
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
                            ->label('Check-in Date & Time')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('detailed_checkout_datetime')
                            ->label('Check-out Date & Time')
                            ->dateTime(),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Additional Services & Payment')
                    ->schema([
                        Infolists\Components\TextEntry::make('additional_requests')
                            ->label('Additional Services')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'None requested';
                                }
                                $serviceNames = collect($state)->map(function ($code) {
                                    $service = Service::where('code', $code)->first();
                                    if ($service) {
                                        return $service->name . ($service->price > 0 ? " ({$service->formatted_price})" : ' (Free)');
                                    }
                                    return $code;
                                })->filter();
                                
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
                            ->dateTime(),
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
