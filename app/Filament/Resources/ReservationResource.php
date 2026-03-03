<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Service;
use App\Models\StayLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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
                            ->tel()
                            ->maxLength(20),
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
                            ->disabled(fn ($record) => $record && $record->guests()->exists())
                            ->dehydrated(fn ($record) => !$record || !$record->guests()->exists()),
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
                Tables\Columns\TextColumn::make('roomAssignments.room.room_number')
                    ->label('Room')
                    ->badge()
                    ->searchable(),
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
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
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

                    // Assign Room action (DISABLED - now handled by check_in)
                    Tables\Actions\Action::make('assign_room')
                        ->icon('heroicon-o-key')
                        ->color('info')
                        ->modalHeading('Assign Room & Collect Guest Information')
                        ->modalWidth('7xl')
                        ->visible(fn (Reservation $record) => false) // Disabled - room assignment now happens during check-in
                        ->form([
                            Forms\Components\Section::make(fn (Reservation $record) => "Room Assignment - {$record->preferredRoomType->name}")
                                ->schema([
                                    Forms\Components\Select::make('room_id')
                                        ->label(fn (Reservation $record) => "Select Room for {$record->preferredRoomType->name}")
                                        ->options(function (Reservation $record) {
                                            return Room::where('status', 'available')
                                                ->where('is_active', true)
                                                ->where('room_type_id', $record->preferred_room_type_id)
                                                ->with('floor')
                                                ->get()
                                                ->mapWithKeys(fn ($room) => [
                                                    $room->id => "Room {$room->room_number} ({$room->floor->name})",
                                                ]);
                                        })
                                        ->required()
                                        ->searchable(),
                                ])->columns(1),

                            Forms\Components\Section::make('Guest Identification')
                                ->schema([
                                    Forms\Components\TextInput::make('guest_last_name')
                                        ->label('Last Name')
                                        ->default(fn (Reservation $record) => $record->guest_last_name)
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('guest_first_name')
                                        ->label('First Name')
                                        ->default(fn (Reservation $record) => $record->guest_first_name)
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('guest_middle_initial')
                                        ->label('Middle Initial')
                                        ->default(fn (Reservation $record) => $record->guest_middle_initial)
                                        ->maxLength(10),
                                    Forms\Components\Textarea::make('guest_full_address')
                                        ->label('Complete Address')
                                        ->default(fn (Reservation $record) => $record->guest_address)
                                        ->rows(2)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('guest_contact_number')
                                        ->label('Contact Number')
                                        ->tel()
                                        ->default(fn (Reservation $record) => $record->guest_phone)
                                        ->required()
                                        ->maxLength(20),
                                ])->columns(3),

                            Forms\Components\Section::make('Identification & Status')
                                ->schema([
                                    Forms\Components\Select::make('id_type')
                                        ->label('ID Type')
                                        ->required()
                                        ->options([
                                            'National ID' => 'National ID',
                                            'Driver\'s License' => 'Driver\'s License',
                                            'Passport' => 'Passport',
                                            'Student ID' => 'Student ID',
                                            'SSS ID' => 'SSS ID',
                                            'UMID' => 'UMID',
                                            'Phil Health ID' => 'Phil Health ID',
                                            'Voter\'s ID' => 'Voter\'s ID',
                                            'Senior Citizen ID' => 'Senior Citizen ID',
                                            'PWD ID' => 'PWD ID',
                                            'Other' => 'Other',
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
                                            'Academic' => 'Academic',
                                            'Official Business' => 'Official Business',
                                            'Personal' => 'Personal',
                                            'Event/Conference' => 'Event/Conference',
                                            'Training' => 'Training',
                                            'Research' => 'Research',
                                            'Other' => 'Other',
                                        ]),
                                    Forms\Components\TextInput::make('num_male_guests')
                                        ->label('Number of Male Guests')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->required(),
                                    Forms\Components\TextInput::make('num_female_guests')
                                        ->label('Number of Female Guests')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->required(),
                                ])->columns(3),

                            Forms\Components\Section::make('Check-in/Check-out Schedule')
                                ->schema([
                                    Forms\Components\DateTimePicker::make('detailed_checkin_datetime')
                                        ->label('Check-in Date & Time')
                                        ->default(fn (Reservation $record) => $record->check_in_date)
                                        ->required()
                                        ->native(false)
                                        ->seconds(false),
                                    Forms\Components\DateTimePicker::make('detailed_checkout_datetime')
                                        ->label('Check-out Date & Time')
                                        ->default(fn (Reservation $record) => $record->check_out_date)
                                        ->required()
                                        ->native(false)
                                        ->seconds(false)
                                        ->after('detailed_checkin_datetime'),
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
                                        ->helperText('Select any additional services needed during the stay. Paid services will be added to the total amount.')
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, Reservation $record) {
                                            // Calculate base room rate
                                            $roomType = $record->preferredRoomType;
                                            $nights = max(1, $record->check_in_date->diffInDays($record->check_out_date));
                                            
                                            $baseRate = $roomType->calculateRate($nights, $record->number_of_occupants);
                                            
                                            // Calculate service charges
                                            $serviceCharges = 0;
                                            if (!empty($state)) {
                                                $services = Service::whereIn('code', $state)->get();
                                                $serviceCharges = $services->sum('price');
                                            }
                                            
                                            // Update payment amount with total
                                            $set('payment_amount', $baseRate + $serviceCharges);
                                        }),
                                    Forms\Components\Select::make('payment_mode')
                                        ->label('Mode of Payment')
                                        ->default('cash')
                                        ->options([
                                            'cash' => 'Cash',
                                            'bank_transfer' => 'Bank Transfer',
                                            'gcash' => 'GCash',
                                            'check' => 'Check',
                                            'others' => 'Others',
                                        ])
                                        ->live()
                                        ->required(),
                                    Forms\Components\TextInput::make('payment_mode_other')
                                        ->label('Specify Payment Mode')
                                        ->visible(fn ($get) => $get('payment_mode') === 'others')
                                        ->maxLength(100),
                                    Forms\Components\Placeholder::make('declared_occupants')
                                        ->label('Declared Number of Guests')
                                        ->content(fn (Reservation $record) => $record->number_of_occupants . ' guest' . ($record->number_of_occupants > 1 ? 's' : ''))
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('payment_amount')
                                        ->label('Total Payment Amount')
                                        ->numeric()
                                        ->prefix('₱')
                                        ->minValue(0)
                                        ->required()
                                        ->helperText(function (Reservation $record) {
                                            $roomType = $record->preferredRoomType;
                                            $nights = max(1, $record->check_in_date->diffInDays($record->check_out_date));
                                            $total = $roomType->calculateRate($nights, $record->number_of_occupants);
                                            
                                            if ($roomType->isPerPersonPricing()) {
                                                return sprintf(
                                                    'Room rate: ₱%s/person/night × %d guest%s × %d night%s = ₱%s. Additional services will be added to this amount.',
                                                    number_format($roomType->base_rate, 2),
                                                    $record->number_of_occupants,
                                                    $record->number_of_occupants > 1 ? 's' : '',
                                                    $nights,
                                                    $nights > 1 ? 's' : '',
                                                    number_format($total, 2)
                                                );
                                            }
                                            
                                            return sprintf(
                                                'Room rate: ₱%s/night × %d night%s = ₱%s. Additional services will be added to this amount.',
                                                number_format($roomType->base_rate, 2),
                                                $nights,
                                                $nights > 1 ? 's' : '',
                                                number_format($total, 2)
                                            );
                                        })
                                        ->default(function (Reservation $record) {
                                            $roomType = $record->preferredRoomType;
                                            $nights = max(1, $record->check_in_date->diffInDays($record->check_out_date));
                                            
                                            // Calculate base room rate using room type's pricing method
                                            return $roomType->calculateRate($nights, $record->number_of_occupants);
                                        }),
                                    Forms\Components\TextInput::make('payment_or_number')
                                        ->label('Official Receipt Number')
                                        ->maxLength(100)
                                        ->required(),
                                    Forms\Components\Textarea::make('notes')
                                        ->label('Assignment Notes')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])->columns(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            // payment_amount already includes service charges (calculated reactively)
                            RoomAssignment::create([
                                'reservation_id' => $record->id,
                                'room_id' => $data['room_id'],
                                'assigned_by' => auth()->id(),
                                'assigned_at' => now(),
                                'notes' => $data['notes'] ?? null,
                                // Guest details
                                'guest_last_name' => $data['guest_last_name'],
                                'guest_first_name' => $data['guest_first_name'],
                                'guest_middle_initial' => $data['guest_middle_initial'] ?? null,
                                'guest_full_address' => $data['guest_full_address'] ?? null,
                                'guest_contact_number' => $data['guest_contact_number'],
                                'id_type' => $data['id_type'],
                                'id_number' => $data['id_number'],
                                'is_student' => $data['is_student'] ?? false,
                                'is_senior_citizen' => $data['is_senior_citizen'] ?? false,
                                'is_pwd' => $data['is_pwd'] ?? false,
                                'purpose_of_stay' => $data['purpose_of_stay'],
                                'nationality' => $data['nationality'],
                                'num_male_guests' => $data['num_male_guests'],
                                'num_female_guests' => $data['num_female_guests'],
                                'detailed_checkin_datetime' => $data['detailed_checkin_datetime'],
                                'detailed_checkout_datetime' => $data['detailed_checkout_datetime'],
                                'additional_requests' => $data['additional_requests'] ?? null,
                                'payment_mode' => $data['payment_mode'],
                                'payment_mode_other' => $data['payment_mode_other'] ?? null,
                                'payment_amount' => $data['payment_amount'], // Total with service charges included
                                'payment_or_number' => $data['payment_or_number'] ?? null,
                            ]);
                        }),

                    // Check In action (includes room assignment & guest details collection)
                    Tables\Actions\Action::make('check_in')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('success')
                        ->modalHeading('Check In Guest - Assign Room & Collect Details')
                        ->modalWidth('7xl')
                        ->visible(fn (Reservation $record) => $record->status === 'approved')
                        ->form([
                            Forms\Components\Section::make(fn (Reservation $record) => "Room Assignment - {$record->preferredRoomType->name}")
                                ->schema([
                                    Forms\Components\Select::make('room_id')
                                        ->label(fn (Reservation $record) => "Select Room for {$record->preferredRoomType->name}")
                                        ->options(function (Reservation $record) {
                                            return Room::where('status', 'available')
                                                ->where('is_active', true)
                                                ->where('room_type_id', $record->preferred_room_type_id)
                                                ->with('floor')
                                                ->get()
                                                ->mapWithKeys(fn ($room) => [
                                                    $room->id => "Room {$room->room_number} ({$room->floor->name})",
                                                ]);
                                        })
                                        ->required()
                                        ->searchable(),
                                ])->columns(1),

                            Forms\Components\Section::make('Guest Identification')
                                ->schema([
                                    Forms\Components\TextInput::make('guest_last_name')
                                        ->label('Last Name')
                                        ->default(fn (Reservation $record) => $record->guest_last_name)
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('guest_first_name')
                                        ->label('First Name')
                                        ->default(fn (Reservation $record) => $record->guest_first_name)
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('guest_middle_initial')
                                        ->label('Middle Initial')
                                        ->default(fn (Reservation $record) => $record->guest_middle_initial)
                                        ->maxLength(10),
                                    Forms\Components\Textarea::make('guest_full_address')
                                        ->label('Complete Address')
                                        ->default(fn (Reservation $record) => $record->guest_address)
                                        ->rows(2)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('guest_contact_number')
                                        ->label('Contact Number')
                                        ->tel()
                                        ->default(fn (Reservation $record) => $record->guest_phone)
                                        ->required()
                                        ->maxLength(20),
                                    Forms\Components\Select::make('guest_gender')
                                        ->label('Gender')
                                        ->required()
                                        ->default(fn (Reservation $record) => $record->guest_gender)
                                        ->options([
                                            'Male' => 'Male',
                                            'Female' => 'Female',
                                            'Other' => 'Other',
                                        ])
                                        ->native(false),
                                ])->columns(3),

                            Forms\Components\Section::make('Identification & Status')
                                ->schema([
                                    Forms\Components\Select::make('id_type')
                                        ->label('ID Type')
                                        ->required()
                                        ->options([
                                            'National ID' => 'National ID',
                                            'Driver\'s License' => 'Driver\'s License',
                                            'Passport' => 'Passport',
                                            'Student ID' => 'Student ID',
                                            'SSS ID' => 'SSS ID',
                                            'UMID' => 'UMID',
                                            'Phil Health ID' => 'Phil Health ID',
                                            'Voter\'s ID' => 'Voter\'s ID',
                                            'Senior Citizen ID' => 'Senior Citizen ID',
                                            'PWD ID' => 'PWD ID',
                                            'Other' => 'Other',
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
                                            'Academic' => 'Academic',
                                            'Official Business' => 'Official Business',
                                            'Personal' => 'Personal',
                                            'Event/Conference' => 'Event/Conference',
                                            'Training' => 'Training',
                                            'Research' => 'Research',
                                            'Other' => 'Other',
                                        ]),
                                    Forms\Components\Hidden::make('num_male_guests')
                                        ->default(0),
                                    Forms\Components\Hidden::make('num_female_guests')
                                        ->default(0),
                                ])->columns(3),

                            Forms\Components\Section::make('Additional Guests')
                                ->description('Add other guests who will be staying (requesting guest is already included)')
                                ->collapsible()
                                ->schema([
                                    Forms\Components\Repeater::make('guests')
                                        ->label('Other Guests')
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
                                                ->options([
                                                    'Male' => 'Male',
                                                    'Female' => 'Female',
                                                    'Other' => 'Other',
                                                ]),
                                        ])
                                        ->columns(4)
                                        ->defaultItems(0)
                                        ->addActionLabel('+ Add Guest')
                                        ->reorderable()
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => 
                                            isset($state['first_name']) && isset($state['last_name']) 
                                                ? $state['first_name'] . ' ' . $state['last_name']
                                                : null
                                        )
                                        ->columnSpanFull(),
                                ]),

                            Forms\Components\Section::make('Check-in/Check-out Schedule')
                                ->schema([
                                    Forms\Components\DateTimePicker::make('detailed_checkin_datetime')
                                        ->label('Check-in Date & Time')
                                        ->default(fn (Reservation $record) => $record->check_in_date)
                                        ->required()
                                        ->native(false)
                                        ->seconds(false),
                                    Forms\Components\DateTimePicker::make('detailed_checkout_datetime')
                                        ->label('Check-out Date & Time')
                                        ->default(fn (Reservation $record) => $record->check_out_date)
                                        ->required()
                                        ->native(false)
                                        ->seconds(false)
                                        ->after('detailed_checkin_datetime'),
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
                                        ->helperText('Select any additional services needed during the stay. Paid services will be added to the total amount.')
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, Reservation $record) {
                                            // Calculate base room rate
                                            $roomType = $record->preferredRoomType;
                                            $nights = max(1, $record->check_in_date->diffInDays($record->check_out_date));
                                            
                                            $baseRate = $roomType->calculateRate($nights, $record->number_of_occupants);
                                            
                                            // Calculate service charges
                                            $serviceCharges = 0;
                                            if (!empty($state)) {
                                                $services = Service::whereIn('code', $state)->get();
                                                $serviceCharges = $services->sum('price');
                                            }
                                            
                                            // Update payment amount with total
                                            $set('payment_amount', $baseRate + $serviceCharges);
                                        }),
                                    Forms\Components\Select::make('payment_mode')
                                        ->label('Mode of Payment')
                                        ->default('cash')
                                        ->options([
                                            'cash' => 'Cash',
                                            'bank_transfer' => 'Bank Transfer',
                                            'gcash' => 'GCash',
                                            'check' => 'Check',
                                            'others' => 'Others',
                                        ])
                                        ->live()
                                        ->required(),
                                    Forms\Components\TextInput::make('payment_mode_other')
                                        ->label('Specify Payment Mode')
                                        ->visible(fn ($get) => $get('payment_mode') === 'others')
                                        ->maxLength(100),
                                    Forms\Components\Placeholder::make('declared_occupants')
                                        ->label('Declared Number of Guests')
                                        ->content(fn (Reservation $record) => $record->number_of_occupants . ' guest' . ($record->number_of_occupants > 1 ? 's' : ''))
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('payment_amount')
                                        ->label('Total Payment Amount')
                                        ->numeric()
                                        ->prefix('₱')
                                        ->minValue(0)
                                        ->required()
                                        ->helperText(function (Reservation $record) {
                                            $roomType = $record->preferredRoomType;
                                            $nights = max(1, $record->check_in_date->diffInDays($record->check_out_date));
                                            $total = $roomType->calculateRate($nights, $record->number_of_occupants);
                                            
                                            if ($roomType->isPerPersonPricing()) {
                                                return sprintf(
                                                    'Room rate: ₱%s/person/night × %d guest%s × %d night%s = ₱%s. Additional services will be added to this amount.',
                                                    number_format($roomType->base_rate, 2),
                                                    $record->number_of_occupants,
                                                    $record->number_of_occupants > 1 ? 's' : '',
                                                    $nights,
                                                    $nights > 1 ? 's' : '',
                                                    number_format($total, 2)
                                                );
                                            }
                                            
                                            return sprintf(
                                                'Room rate: ₱%s/night × %d night%s = ₱%s. Additional services will be added to this amount.',
                                                number_format($roomType->base_rate, 2),
                                                $nights,
                                                $nights > 1 ? 's' : '',
                                                number_format($total, 2)
                                            );
                                        })
                                        ->default(function (Reservation $record) {
                                            $roomType = $record->preferredRoomType;
                                            $nights = max(1, $record->check_in_date->diffInDays($record->check_out_date));
                                            
                                            // Calculate base room rate using room type's pricing method
                                            return $roomType->calculateRate($nights, $record->number_of_occupants);
                                        }),
                                    Forms\Components\TextInput::make('payment_or_number')
                                        ->label('Official Receipt Number')
                                        ->maxLength(100)
                                        ->required(),
                                    Forms\Components\Textarea::make('remarks')
                                        ->label('Check-in Remarks')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])->columns(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            // Create room assignment
                            $assignment = RoomAssignment::create([
                                'reservation_id' => $record->id,
                                'room_id' => $data['room_id'],
                                'assigned_by' => auth()->id(),
                                'assigned_at' => now(),
                                'notes' => $data['remarks'] ?? null,
                                // Guest details
                                'guest_last_name' => $data['guest_last_name'],
                                'guest_first_name' => $data['guest_first_name'],
                                'guest_middle_initial' => $data['guest_middle_initial'] ?? null,
                                'guest_full_address' => $data['guest_full_address'] ?? null,
                                'guest_contact_number' => $data['guest_contact_number'],
                                'id_type' => $data['id_type'],
                                'id_number' => $data['id_number'],
                                'is_student' => $data['is_student'] ?? false,
                                'is_senior_citizen' => $data['is_senior_citizen'] ?? false,
                                'is_pwd' => $data['is_pwd'] ?? false,
                                'purpose_of_stay' => $data['purpose_of_stay'],
                                'nationality' => $data['nationality'],
                                'num_male_guests' => $data['num_male_guests'],
                                'num_female_guests' => $data['num_female_guests'],
                                'detailed_checkin_datetime' => $data['detailed_checkin_datetime'],
                                'detailed_checkout_datetime' => $data['detailed_checkout_datetime'],
                                'additional_requests' => $data['additional_requests'] ?? null,
                                'payment_mode' => $data['payment_mode'],
                                'payment_mode_other' => $data['payment_mode_other'] ?? null,
                                'payment_amount' => $data['payment_amount'],
                                'payment_or_number' => $data['payment_or_number'] ?? null,
                            ]);

                            // Create stay log
                            StayLog::create([
                                'reservation_id' => $record->id,
                                'room_id' => $data['room_id'],
                                'checked_in_at' => now(),
                                'checked_in_by' => auth()->id(),
                                'remarks' => $data['remarks'] ?? null,
                            ]);

                            // Update room status to occupied
                            $assignment->room->update(['status' => 'occupied']);

                            // Update reservation status to checked_in and save gender
                            $record->update([
                                'status' => 'checked_in',
                                'guest_gender' => $data['guest_gender'] ?? null,
                            ]);

                            // Save requesting guest
                            $requestingGuestFullName = trim(
                                $record->guest_first_name . ' ' .
                                ($record->guest_middle_initial ? $record->guest_middle_initial . ' ' : '') .
                                $record->guest_last_name
                            );
                            
                            Guest::create([
                                'reservation_id' => $record->id,
                                'full_name' => $requestingGuestFullName,
                                'first_name' => $record->guest_first_name,
                                'last_name' => $record->guest_last_name,
                                'middle_initial' => $record->guest_middle_initial,
                                'gender' => $data['guest_gender'] ?? null,
                            ]);

                            // Save additional guests from the form
                            if (!empty($data['guests'])) {
                                foreach ($data['guests'] as $guestData) {
                                    $fullName = trim(
                                        ($guestData['first_name'] ?? '') . ' ' .
                                        ($guestData['middle_initial'] ? $guestData['middle_initial'] . ' ' : '') .
                                        ($guestData['last_name'] ?? '')
                                    );
                                    
                                    Guest::create([
                                        'reservation_id' => $record->id,
                                        'full_name' => $fullName,
                                        'first_name' => $guestData['first_name'] ?? null,
                                        'last_name' => $guestData['last_name'] ?? null,
                                        'middle_initial' => $guestData['middle_initial'] ?? null,
                                        'gender' => $guestData['gender'] ?? null,
                                    ]);
                                }
                            }

                            // Recalculate counts based on actual guest records
                            $totalGuests = $record->guests()->count();
                            $maleCount = $record->guests()->where('gender', 'Male')->count();
                            $femaleCount = $record->guests()->where('gender', 'Female')->count();
                            
                            $record->update([
                                'number_of_occupants' => $totalGuests,
                                'num_male_guests' => $maleCount,
                                'num_female_guests' => $femaleCount,
                            ]);
                            
                            $assignment->update([
                                'num_male_guests' => $maleCount,
                                'num_female_guests' => $femaleCount,
                            ]);
                        }),

                    // Check Out action
                    Tables\Actions\Action::make('check_out')
                        ->icon('heroicon-o-arrow-left-on-rectangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Check Out Guest')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in')
                        ->form([
                            Forms\Components\Textarea::make('remarks')
                                ->label('Check-out Remarks')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->update(['status' => 'checked_out']);

                            foreach ($record->stayLogs()->whereNull('checked_out_at')->get() as $log) {
                                $log->update([
                                    'checked_out_at' => now(),
                                    'checked_out_by' => auth()->id(),
                                    'remarks' => $data['remarks'] ? ($log->remarks ? $log->remarks . ' | ' . $data['remarks'] : $data['remarks']) : $log->remarks,
                                ]);
                                $log->room->update(['status' => 'available']);
                            }
                        }),

                    // Cancel action
                    Tables\Actions\Action::make('cancel')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Reservation $record) => in_array($record->status, ['pending', 'approved']))
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Cancellation reason')
                                ->required()
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
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
            ReservationResource\RelationManagers\StayLogsRelationManager::class,
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
