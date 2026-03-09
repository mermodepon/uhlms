<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Service;
use App\Services\CheckInService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Guest Information')
                    ->schema([
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
                        Forms\Components\TextInput::make('guest_email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('guest_phone')
                            ->maxLength(30),
                        Forms\Components\Select::make('guest_gender')
                            ->label('Gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                                'Other' => 'Other',
                            ])
                            ->native(false),
                        Forms\Components\Textarea::make('guest_address')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Reservation Details')
                    ->schema([
                        Forms\Components\TextInput::make('reference_number')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn(['edit', 'view']),
                        Forms\Components\Select::make('preferred_room_type_id')
                            ->relationship('preferredRoomType', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->required()
                            ->preload()
                            ->searchable(),
                        Forms\Components\DatePicker::make('check_in_date')
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('check_out_date')
                            ->required()
                            ->after('check_in_date')
                            ->native(false),
                        Forms\Components\TextInput::make('number_of_occupants')
                            ->label('Number of Occupants')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(1)
                            ->visibleOn('create'),
                        Forms\Components\Select::make('purpose')
                            ->options([
                                'academic' => 'Academic',
                                'official' => 'Official Business',
                                'personal' => 'Personal',
                                'event' => 'Event / Conference',
                                'other' => 'Other',
                            ]),
                        Forms\Components\Textarea::make('special_requests')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Status & Review')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'pending_payment' => 'Pending Payment',
                                'declined' => 'Declined',
                                'cancelled' => 'Cancelled',
                                'checked_in' => 'Checked In',
                                'checked_out' => 'Checked Out',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Staff Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('guest_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guest_email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('preferredRoomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('room_display')
                    ->label('Room')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $rooms = $record->roomAssignments
                            ->pluck('room.room_number')
                            ->filter()
                            ->unique()
                            ->values()
                            ->toArray();

                        return empty($rooms) ? null : (count($rooms) === 1 ? $rooms[0] : $rooms);
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('roomAssignments.room', fn ($q) =>
                            $q->where('room_number', 'like', "%{$search}%")
                        );
                    }),
                Tables\Columns\TextColumn::make('check_in_date')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out_date')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn ($state, $record): string => match (true) {
                        $state === 'pending'                                          => 'warning',
                        $state === 'approved' && $record->roomAssignments->isEmpty() => 'info',
                        $state === 'approved'                                        => 'primary',
                        $state === 'pending_payment'                                 => 'warning',
                        $state === 'declined'                                        => 'danger',
                        $state === 'cancelled'                                       => 'gray',
                        $state === 'checked_in'                                      => 'success',
                        $state === 'checked_out'                                     => 'gray',
                        default                                                      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->label('Submitted'),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['roomAssignments.room', 'preferredRoomType']))
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'pending_payment' => 'Pending Payment',
                        'declined' => 'Declined',
                        'cancelled' => 'Cancelled',
                        'checked_in' => 'Checked In',
                        'checked_out' => 'Checked out',
                    ]),
                Tables\Filters\SelectFilter::make('preferred_room_type_id')
                    ->relationship('preferredRoomType', 'name')
                    ->label('Room Type')
                    ->preload(),
                Tables\Filters\Filter::make('check_in_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('check_in_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('check_in_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Approve action
                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Reservation')
                        ->modalDescription('Approve this reservation? The guest should proceed to the front desk for check-in, where room assignment and payment will be processed.')
                        ->visible(fn (Reservation $record) => $record->status === 'pending')
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Notes (optional)')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->update([
                                'status' => 'approved',
                                'admin_notes' => $data['admin_notes'] ?? $record->admin_notes,
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        }),

                    // Decline action
                    Tables\Actions\Action::make('decline')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Decline Reservation')
                        ->visible(fn (Reservation $record) => $record->status === 'pending')
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Reason for declining')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->update([
                                'status' => 'declined',
                                'admin_notes' => $data['admin_notes'],
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        }),

                    // Prepare Check-in action (locks room/bed while payment is processed)
                    Tables\Actions\Action::make('prepare_check_in')
                        ->label('Prepare Check-in')
                        ->icon('heroicon-o-lock-closed')
                        ->color('success')
                        ->modalHeading('Prepare Check-in (Pending Payment)')
                        ->modalWidth('7xl')
                        ->visible(fn (Reservation $record) => $record->status === 'approved')
                        ->form([
                            Forms\Components\Section::make('Primary Guest Identification')
                                ->schema([
                                    Forms\Components\TextInput::make('guest_last_name')
                                        ->label('Last Name')
                                        ->default(fn (Reservation $record) => $record->guest_last_name)
                                        ->required()
                                        ->maxLength(255)
                                        ->live()
                                        ->dehydrated(),
                                    Forms\Components\TextInput::make('guest_first_name')
                                        ->label('First Name')
                                        ->default(fn (Reservation $record) => $record->guest_first_name)
                                        ->required()
                                        ->maxLength(255)
                                        ->live()
                                        ->dehydrated(),
                                    Forms\Components\TextInput::make('guest_middle_initial')
                                        ->label('Middle Initial')
                                        ->default(fn (Reservation $record) => $record->guest_middle_initial)
                                        ->maxLength(10)
                                        ->live()
                                        ->dehydrated(),
                                    Forms\Components\Select::make('guest_gender')
                                        ->label('Gender')
                                        ->required()
                                        ->default(fn (Reservation $record) => $record->guest_gender)
                                        ->options([
                                            'Male'   => 'Male',
                                            'Female' => 'Female',
                                            'Other'  => 'Other',
                                        ])
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function ($set) {
                                            $set('room_id', null);
                                            $set('bed_ids', []);
                                        }),
                                    Forms\Components\Textarea::make('guest_full_address')
                                        ->label('Complete Address')
                                        ->default(fn (Reservation $record) => $record->guest_address)
                                        ->rows(2)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('guest_contact_number')
                                        ->label('Contact Number')
                                        ->default(fn (Reservation $record) => $record->guest_phone)
                                        ->required()
                                        ->maxLength(30),
                                ])->columns(3),

                            Forms\Components\Section::make('Room Entries')
                                ->description(fn (Reservation $record) => $record->preferredRoomType->isPrivate()
                                    ? 'Add one row per room involved in this check-in. Private reservations are room-level only (no bed assignment).'
                                    : 'Add one row per room involved in this check-in. Use PRIVATE for whole-room assignment or DORM for per-bed assignment.')
                                ->schema([
                                    Forms\Components\Repeater::make('reservation_rooms')
                                        ->schema([
                                            Forms\Components\Select::make('room_mode')
                                                ->label('Room Mode')
                                                ->required()
                                                ->options([
                                                    'private' => 'Private (occupies whole room)',
                                                    'dorm' => 'Dorm (per-bed assignment)',
                                                ])
                                                ->placeholder('Select an option')
                                                ->dehydrated()
                                                ->native(false)
                                                ->live()
                                                ->afterStateUpdated(function ($state, $old, $set) {
                                                    // Only reset conflicting room allocation selectors when mode changes.
                                                    // Keep typed guest rows intact to avoid accidental data loss.
                                                    if ($state === $old) {
                                                        return;
                                                    }

                                                    $set('room_id', null);
                                                    $set('includes_primary_guest', false);
                                                })
                                                ->helperText('Choose how to allocate this room to guests'),
                                            Forms\Components\Select::make('room_id')
                                                ->label('Room')
                                                ->required()
                                                ->searchable()
                                                ->preload()
                                                ->options(function ($get, Reservation $record) {
                                                    app(CheckInService::class)->releaseExpiredHolds();

                                                    $mode = $get('room_mode');
                                                    if (! in_array($mode, ['private', 'dorm'], true)) {
                                                        return [];
                                                    }

                                                    $preferredTypeId = $record->preferred_room_type_id;
                                                    $preferredTypeName = $record->preferredRoomType->name;
                                                    
                                                    $query = Room::query()
                                                        ->with('roomType', 'beds')
                                                        ->where('is_active', true);

                                                    // Dorm mode: must have available beds
                                                    // Private mode: room must be available
                                                    if ($mode === 'dorm') {
                                                        $query->whereHas('beds', fn ($q) => $q->where('status', 'available'));
                                                    } else {
                                                        $query->where('status', 'available');
                                                    }

                                                    $rooms = $query->get();
                                                    if ($rooms->isEmpty()) {
                                                        return ['' => '(No available rooms)'];
                                                    }

                                                    // Group by room type with preferred first
                                                    $grouped = $rooms->groupBy('room_type_id')->sortBy(function ($group, $typeId) use ($preferredTypeId) {
                                                        return $typeId == $preferredTypeId ? 0 : 1;
                                                    });
                                                    
                                                    $options = [];
                                                    foreach ($grouped as $typeId => $roomsInType) {
                                                        $typeName = $roomsInType->first()->roomType->name;
                                                        $isPreferred = $typeId == $preferredTypeId;
                                                        $groupLabel = $isPreferred ? "⭐ {$typeName} (Preferred)" : $typeName;
                                                        
                                                        $options[$groupLabel] = $roomsInType->mapWithKeys(fn ($room) => [
                                                            $room->id => "Room {$room->room_number} | {$room->getGenderLabel()}",
                                                        ])->toArray();
                                                    }
                                                    
                                                    return $options;
                                                })
                                                ->helperText(fn ($get) => filled($get('room_mode') ?? null)
                                                    ? 'Preferred room type shown first'
                                                    : 'Select room mode first'),
                                            Forms\Components\Toggle::make('includes_primary_guest')
                                                ->label('Include primary guest in this room')
                                                ->helperText('Primary guest details above are auto-included when enabled.')
                                                ->default(false)
                                                ->inline(false)
                                                ->visible(fn ($get) => filled($get('room_mode') ?? null) && filled($get('room_id') ?? null))
                                                ->live()
                                                ->afterStateUpdated(function ($state, $get, $set, $component) {
                                                    // Only enforce exclusivity when a room is explicitly toggled ON.
                                                    // Avoid auto-corrections on OFF updates to prevent toggle flicker.
                                                    if ($state !== true) {
                                                        return;
                                                    }

                                                    $entries = $get('../../reservation_rooms') ?? [];
                                                    if (! is_array($entries) || empty($entries)) {
                                                        return;
                                                    }

                                                    $statePath = method_exists($component, 'getStatePath')
                                                        ? (string) $component->getStatePath()
                                                        : '';
                                                    $pathParts = $statePath !== '' ? explode('.', $statePath) : [];
                                                    $itemKey = count($pathParts) >= 2 ? $pathParts[count($pathParts) - 2] : null;

                                                    if ($state === true && $itemKey !== null) {
                                                        foreach ($entries as $key => $entry) {
                                                            $set(
                                                                '../../reservation_rooms.' . $key . '.includes_primary_guest',
                                                                ((string) $key === (string) $itemKey)
                                                            );
                                                        }

                                                        return;
                                                    }
                                                }),
                                            Forms\Components\Repeater::make('guests')
                                                ->label(function ($get) {
                                                    $roomId = $get('room_id');
                                                    if ($roomId) {
                                                        $room = \App\Models\Room::find($roomId);
                                                        return $room ? "Guests for Room {$room->room_number}" : 'Guests for selected room';
                                                    }
                                                    return 'Guests for selected room';
                                                })
                                                ->schema([
                                                    Forms\Components\TextInput::make('last_name')
                                                        ->label('Last Name')
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->live(onBlur: true),
                                                    Forms\Components\TextInput::make('first_name')
                                                        ->label('First Name')
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->live(onBlur: true),
                                                    Forms\Components\TextInput::make('middle_initial')
                                                        ->label('M.I.')
                                                        ->maxLength(10)
                                                        ->live(onBlur: true),
                                                    Forms\Components\Select::make('gender')
                                                        ->label('Gender')
                                                        ->required()
                                                        ->options([
                                                            'Male' => 'Male',
                                                            'Female' => 'Female',
                                                            'Other' => 'Other',
                                                        ])
                                                        ->native(false)
                                                        ->live(),
                                                ])
                                                ->columns(4)
                                                ->minItems(0)
                                                ->defaultItems(0)
                                                ->addActionLabel('➕ Add Another Guest')
                                                ->helperText('Add companion guests only. Primary guest is auto-included when enabled above.')
                                                ->visible(fn ($get) => filled($get('room_mode') ?? null) && filled($get('room_id') ?? null))
                                                ->reorderable(false),
                                        ])
                                        ->defaultItems(1)
                                        ->minItems(1)
                                        ->reorderable(false)
                                        ->afterStateUpdated(function ($state, $get, $set, Reservation $record) {
                                            // No direct payment field mutation here to avoid reactive state races.
                                        })
                                        ->columnSpanFull()
                                        ->addActionLabel('➕ Add Another Room')
                                        ->live(),
                                ]),

                            Forms\Components\Section::make('Identification & Status')
                                ->schema([
                                    Forms\Components\Select::make('id_type')
                                        ->label('ID Type')
                                        ->required()
                                        ->options([
                                            'National ID'       => 'National ID',
                                            "Driver's License"  => "Driver's License",
                                            'Passport'          => 'Passport',
                                            'Student ID'        => 'Student ID',
                                            'SSS ID'            => 'SSS ID',
                                            'UMID'              => 'UMID',
                                            'Phil Health ID'    => 'Phil Health ID',
                                            "Voter's ID"        => "Voter's ID",
                                            'Senior Citizen ID' => 'Senior Citizen ID',
                                            'PWD ID'            => 'PWD ID',
                                            'Other'             => 'Other',
                                        ])
                                        ->searchable(),
                                    Forms\Components\TextInput::make('id_number')
                                        ->label('ID Number')
                                        ->required()
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('nationality')
                                        ->label('Nationality')
                                        ->default('Filipino')
                                        ->required()
                                        ->maxLength(100),
                                    Forms\Components\Toggle::make('is_student')
                                        ->label('Student')
                                        ->inline(false),
                                    Forms\Components\Toggle::make('is_senior_citizen')
                                        ->label('Senior Citizen')
                                        ->inline(false),
                                    Forms\Components\Toggle::make('is_pwd')
                                        ->label('PWD')
                                        ->inline(false),
                                ])->columns(3),

                            Forms\Components\Section::make('Stay Details')
                                ->schema([
                                    Forms\Components\Select::make('purpose_of_stay')
                                        ->label('Purpose of Stay')
                                        ->default(fn (Reservation $record) => ucwords(str_replace('_', ' ', $record->purpose ?? 'personal')))
                                        ->required()
                                        ->options([
                                            'Academic'          => 'Academic',
                                            'Official Business' => 'Official Business',
                                            'Personal'          => 'Personal',
                                            'Event/Conference'  => 'Event/Conference',
                                            'Training'          => 'Training',
                                            'Research'          => 'Research',
                                            'Other'             => 'Other',
                                        ]),
                                    Forms\Components\Hidden::make('num_male_guests')->default(0),
                                    Forms\Components\Hidden::make('num_female_guests')->default(0),
                                ])->columns(1),

                            Forms\Components\Section::make('Check-in / Check-out Schedule')
                                ->schema([
                                    Forms\Components\DateTimePicker::make('detailed_checkin_datetime')
                                        ->label('Check-in Date & Time')
                                        ->default(fn (Reservation $record) => $record->check_in_date)
                                        ->required()
                                        ->native(false)
                                        ->seconds(false)
                                        ->live(),
                                    Forms\Components\DateTimePicker::make('detailed_checkout_datetime')
                                        ->label('Check-out Date & Time')
                                        ->default(fn (Reservation $record) => $record->check_out_date)
                                        ->required()
                                        ->native(false)
                                        ->seconds(false)
                                        ->after('detailed_checkin_datetime')
                                        ->live(),
                                ])->columns(2),

                            Forms\Components\Section::make('Additional Services & Payment')
                                ->schema([
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
                                        ->helperText('Paid services will be added to the total amount.')
                                        ->live(),
                                    Forms\Components\Select::make('payment_mode')
                                        ->label('Mode of Payment')
                                        ->default('cash')
                                        ->options([
                                            'cash'          => 'Cash',
                                            'bank_transfer' => 'Bank Transfer',
                                            'gcash'         => 'GCash',
                                            'check'         => 'Check',
                                            'others'        => 'Others',
                                        ])
                                        ->live()
                                        ->required(),
                                    Forms\Components\TextInput::make('payment_mode_other')
                                        ->label('Specify Payment Mode')
                                        ->visible(fn ($get) => $get('payment_mode') === 'others')
                                        ->maxLength(100),
                                    Forms\Components\Placeholder::make('declared_occupants')
                                        ->label('Declared Number of Guests')
                                        ->content(fn (Reservation $record) => $record->number_of_occupants . ' guest' . ($record->number_of_occupants > 1 ? 's' : '')),
                                    Forms\Components\Placeholder::make('declared_days')
                                        ->label('Declared Number of Days')
                                        ->content(function ($get, Reservation $record) {
                                            $checkIn = $get('detailed_checkin_datetime');
                                            $checkOut = $get('detailed_checkout_datetime');
                                            
                                            if ($checkIn && $checkOut) {
                                                $checkInDate = \Carbon\Carbon::parse($checkIn);
                                                $checkOutDate = \Carbon\Carbon::parse($checkOut);
                                                $d = max(1, $checkInDate->diffInDays($checkOutDate));
                                            } else {
                                                $d = max(1, $record->check_in_date->diffInDays($record->check_out_date));
                                            }
                                            
                                            return $d . ' day' . ($d > 1 ? 's' : '');
                                        }),
                                    Forms\Components\TextInput::make('payment_amount')
                                        ->label('Total Payment Amount')
                                        ->numeric()
                                        ->prefix('₱')
                                        ->minValue(0)
                                        ->required()
                                        ->default(function (Reservation $record) {
                                            $pricing = self::computeCheckInPricing(
                                                $record,
                                                [],
                                                null,
                                                null,
                                                []
                                            );

                                            return $pricing['grand_total'];
                                        }),
                                    Forms\Components\Placeholder::make('live_checkin_pricing_breakdown')
                                        ->label('Current Updated Price (Actual Check-in Data)')
                                        ->content(function ($get, Reservation $record) {
                                            $pricing = self::computeCheckInPricing(
                                                $record,
                                                $get('reservation_rooms') ?? [],
                                                $get('detailed_checkin_datetime'),
                                                $get('detailed_checkout_datetime'),
                                                $get('additional_requests') ?? []
                                            );

                                            $rows = [];
                                            foreach ($pricing['rooms'] as $line) {
                                                $rows[] = sprintf(
                                                    '<li>%s: %s</li>',
                                                    e($line['label']),
                                                    e($line['formula'])
                                                );
                                            }

                                            if (empty($rows)) {
                                                $rows[] = '<li>Select room(s) and guest(s) to preview real-time computation.</li>';
                                            }

                                            $html = '<div class="text-sm space-y-2">';
                                            $html .= '<div><strong>Nights:</strong> ' . $pricing['nights'] . '</div>';
                                            $html .= '<ul class="list-disc pl-5 space-y-1">' . implode('', $rows) . '</ul>';
                                            $html .= '<div><strong>Room Subtotal:</strong> ₱' . number_format($pricing['room_subtotal'], 2) . '</div>';
                                            $html .= '<div><strong>Services:</strong> ₱' . number_format($pricing['services_total'], 2) . '</div>';
                                            $html .= '<div class="font-semibold"><strong>Updated Total:</strong> ₱' . number_format($pricing['grand_total'], 2) . '</div>';
                                            $html .= '</div>';

                                            return new HtmlString($html);
                                        })
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('payment_or_number')
                                        ->label('Official Receipt Number')
                                        ->maxLength(100)
                                        ->helperText('Optional for now. You can finalize this after payment is made.'),
                                    Forms\Components\Textarea::make('remarks')
                                        ->label('Check-in Remarks')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])->columns(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            try {
                                $pricing = self::computeCheckInPricing(
                                    $record,
                                    $data['reservation_rooms'] ?? [],
                                    $data['detailed_checkin_datetime'] ?? null,
                                    $data['detailed_checkout_datetime'] ?? null,
                                    $data['additional_requests'] ?? []
                                );
                                $data['payment_amount'] = $pricing['grand_total'];

                                $result = app(CheckInService::class)->preparePendingPayment($record, $data);

                                Notification::make()
                                    ->success()
                                    ->title('Check-in Prepared')
                                    ->body('Hold created for ' . $result['held_guest_count'] . ' guest(s). Expires at ' . optional($result['hold_expires_at'])?->format('M d, Y h:i A') . '.')
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Unable to Prepare Check-in')
                                    ->body($e->getMessage())
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('finalize_check_in')
                        ->label('Finalize Check-in')
                        ->icon('heroicon-o-credit-card')
                        ->color('warning')
                        ->modalHeading('Finalize Check-in After Payment')
                        ->visible(fn (Reservation $record) => $record->status === 'pending_payment')
                        ->form([
                            Forms\Components\Placeholder::make('hold_expiry_notice')
                                ->label('Hold Status')
                                ->content(fn (Reservation $record) => $record->checkin_hold_expires_at
                                    ? 'Hold expires on ' . $record->checkin_hold_expires_at->format('M d, Y h:i A')
                                    : 'No hold expiry recorded.'),
                            Forms\Components\Placeholder::make('payable_amount_notice')
                                ->label('Payable Amount')
                                ->content(function (Reservation $record) {
                                    $payable = (float) (data_get($record->checkin_hold_payload, 'payload.payment_amount') ?? 0);

                                    return $payable > 0
                                        ? '₱' . number_format($payable, 2)
                                        : 'No payable amount recorded in hold payload.';
                                }),
                            Forms\Components\Select::make('payment_mode')
                                ->label('Mode of Payment')
                                ->default(fn (Reservation $record) => data_get($record->checkin_hold_payload, 'payload.payment_mode') ?? 'cash')
                                ->options([
                                    'cash'          => 'Cash',
                                    'bank_transfer' => 'Bank Transfer',
                                    'gcash'         => 'GCash',
                                    'check'         => 'Check',
                                    'others'        => 'Others',
                                ])
                                ->live()
                                ->required(),
                            Forms\Components\TextInput::make('payment_mode_other')
                                ->label('Specify Payment Mode')
                                ->visible(fn ($get) => $get('payment_mode') === 'others')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('payment_amount')
                                ->label('Paid Amount')
                                ->numeric()
                                ->prefix('₱')
                                ->default(fn (Reservation $record) => (float) (data_get($record->checkin_hold_payload, 'payload.payment_amount') ?? 0))
                                ->required(),
                            Forms\Components\TextInput::make('payment_or_number')
                                ->label('Official Receipt Number')
                                ->required()
                                ->maxLength(100),
                            Forms\Components\Textarea::make('remarks')
                                ->label('Final Check-in Remarks')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            try {
                                $result = app(CheckInService::class)->finalizePendingPayment($record, $data);

                                if (($result['all_succeeded'] ?? false) === true) {
                                    Notification::make()
                                        ->success()
                                        ->title('Reservation Checked In')
                                        ->body("Checked in {$result['checked_in_count']} guest(s) successfully.")
                                        ->send();

                                    return;
                                }

                                $messages = array_merge(
                                    $result['room_errors'] ?? [],
                                    $result['failed_guests'] ?? []
                                );

                                Notification::make()
                                    ->warning()
                                    ->title('Finalization Completed With Issues')
                                    ->body(implode(' ', array_slice($messages, 0, 5)))
                                    ->persistent()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Unable to Finalize Check-in')
                                    ->body($e->getMessage())
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('cancel_payment_hold')
                        ->label('Cancel Payment Hold')
                        ->icon('heroicon-o-lock-open')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Release Hold and Return to Approved')
                        ->visible(fn (Reservation $record) => $record->status === 'pending_payment')
                        ->action(function (Reservation $record) {
                            app(CheckInService::class)->releasePendingPaymentHold($record, true);

                            Notification::make()
                                ->success()
                                ->title('Hold Released')
                                ->body('Room/bed locks were released and reservation is back to Approved.')
                                ->send();
                        }),

                    // Check Out action
                    Tables\Actions\Action::make('check_out')
                        ->icon('heroicon-o-arrow-left-on-rectangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Check Out Guest')
                        ->visible(fn (Reservation $record) => in_array($record->status, ['checked_in', 'checked_out'], true))
                        ->form([
                            Forms\Components\Textarea::make('remarks')
                                ->label('Check-out Remarks')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            // Close ALL room assignments without a checkout timestamp
                            RoomAssignment::where('reservation_id', $record->id)
                                ->whereNull('checked_out_at')
                                ->update([
                                    'status' => 'checked_out',
                                    'checked_out_at' => now(),
                                    'checked_out_by' => auth()->id(),
                                ]);

                            // Add remarks if provided to all assignments
                            if ($data['remarks']) {
                                RoomAssignment::where('reservation_id', $record->id)
                                    ->each(function ($assignment) use ($data) {
                                        $assignment->update([
                                            'remarks' => $assignment->remarks
                                                ? $assignment->remarks . ' | ' . $data['remarks']
                                                : $data['remarks'],
                                        ]);
                                    });
                            }

                            $record->update(['status' => 'checked_out']);

                            Notification::make()
                                ->success()
                                ->title('Checked Out')
                                ->body('All guests have been checked out successfully.')
                                ->send();
                        }),

                    // Cancel action
                    Tables\Actions\Action::make('cancel')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Reservation $record) => in_array($record->status, ['pending', 'approved', 'pending_payment']))
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Cancellation reason')
                                ->required()
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            if ($record->status === 'pending_payment') {
                                app(CheckInService::class)->releasePendingPaymentHold($record, false);
                            }

                            $record->update([
                                'status' => 'cancelled',
                                'admin_notes' => $data['admin_notes'],
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->successNotificationTitle('Reservations deleted'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReservationResource\RelationManagers\RoomAssignmentsRelationManager::class,
        ];
    }

    /**
     * Apply friendly query parameters to the base Eloquent query.
     * Supports `status` (single or comma-separated), `near_due=1`, and `overdue=1`.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $req = request();

        if ($status = $req->query('status')) {
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } elseif (str_contains($status, ',')) {
                $query->whereIn('status', array_map('trim', explode(',', $status)));
            } else {
                $query->where('status', $status);
            }
        }

        if ($req->boolean('near_due')) {
            $from = now()->toDateString();
            $until = now()->copy()->addDay()->toDateString();
            $query->where('status', 'checked_in')
                  ->whereBetween('check_out_date', [$from, $until]);
        }

        if ($req->boolean('overdue')) {
            $query->where('status', 'checked_in')
                  ->whereDate('check_out_date', '<', now()->toDateString());
        }

        return $query;
    }

    protected static function computeCheckInPricing(
        Reservation $record,
        array $reservationRooms,
        mixed $checkInState,
        mixed $checkOutState,
        array $serviceCodes
    ): array {
        $checkIn = $checkInState ? Carbon::parse($checkInState) : Carbon::parse($record->check_in_date);
        $checkOut = $checkOutState ? Carbon::parse($checkOutState) : Carbon::parse($record->check_out_date);
        $nights = max(1, $checkIn->diffInDays($checkOut));

        $roomIds = collect($reservationRooms)
            ->pluck('room_id')
            ->filter()
            ->unique()
            ->values();

        $roomsById = Room::with('roomType')
            ->whereIn('id', $roomIds)
            ->get()
            ->keyBy('id');

        $roomLines = [];
        $roomSubtotal = 0.0;

        foreach ($reservationRooms as $entry) {
            $roomId = $entry['room_id'] ?? null;
            if (! $roomId || ! $roomsById->has($roomId)) {
                continue;
            }

            $room = $roomsById->get($roomId);
            $roomType = $room->roomType;
            $companionCount = collect($entry['guests'] ?? [])
                ->filter(fn ($guest) => filled($guest['first_name'] ?? null) || filled($guest['last_name'] ?? null))
                ->count();
            $guestCount = $companionCount + ((bool) ($entry['includes_primary_guest'] ?? false) ? 1 : 0);
            $rate = (float) $roomType->base_rate;
            $roomMode = $entry['room_mode'] ?? ($roomType->isPrivate() ? 'private' : 'dorm');

            // Match pricing basis to selected allocation mode in the check-in form.
            $isPerBedPricing = $roomMode === 'dorm';

            if ($isPerBedPricing) {
                $lineTotal = $rate * $guestCount * $nights;
                $formula = sprintf(
                    '₱%s x %d guest(s) x %d night(s) = ₱%s',
                    number_format($rate, 2),
                    $guestCount,
                    $nights,
                    number_format($lineTotal, 2)
                );
            } else {
                $lineTotal = $rate * $nights;
                $formula = sprintf(
                    '₱%s x %d night(s) = ₱%s',
                    number_format($rate, 2),
                    $nights,
                    number_format($lineTotal, 2)
                );
            }

            $roomSubtotal += $lineTotal;
            $roomLines[] = [
                'label' => "Room {$room->room_number} ({$roomType->name}, " . ucfirst($roomMode) . ')',
                'formula' => $formula,
                'line_total' => $lineTotal,
            ];
        }

        $servicesTotal = empty($serviceCodes)
            ? 0.0
            : (float) Service::whereIn('code', $serviceCodes)->sum('price');

        // Fallback to declared reservation pricing when no room lines are available yet.
        if (empty($roomLines)) {
            $declaredBase = (float) $record->preferredRoomType->calculateRate($nights, (int) $record->number_of_occupants);
            return [
                'nights' => $nights,
                'rooms' => [],
                'room_subtotal' => $declaredBase,
                'services_total' => $servicesTotal,
                'grand_total' => $declaredBase + $servicesTotal,
            ];
        }

        return [
            'nights' => $nights,
            'rooms' => $roomLines,
            'room_subtotal' => $roomSubtotal,
            'services_total' => $servicesTotal,
            'grand_total' => $roomSubtotal + $servicesTotal,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'view' => Pages\ViewReservation::route('/{record}'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
