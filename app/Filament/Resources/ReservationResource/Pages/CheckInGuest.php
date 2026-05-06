<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Services\CheckInService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class CheckInGuest extends Page
{
    protected static string $resource = ReservationResource::class;

    protected static string $view = 'filament.resources.reservation-resource.pages.check-in-guest';

    protected static ?string $title = 'Check In Guest';

    public ?array $data = [];

    public Reservation $record;

    public function mount(Reservation $record): void
    {
        $this->record = $record;

        abort_unless(in_array($record->status, ['approved', 'confirmed']), 403, 'This reservation cannot be checked in.');

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Primary Guest Identification')
                ->schema([
                    Forms\Components\TextInput::make('guest_last_name')
                        ->label('Last Name')
                        ->default(fn () => $this->record->guest_last_name)
                        ->required()
                        ->maxLength(255)
                        ->dehydrated(),
                    Forms\Components\TextInput::make('guest_first_name')
                        ->label('First Name')
                        ->default(fn () => $this->record->guest_first_name)
                        ->required()
                        ->maxLength(255)
                        ->dehydrated(),
                    Forms\Components\TextInput::make('guest_middle_initial')
                        ->label('Middle Initial')
                        ->default(fn () => $this->record->guest_middle_initial)
                        ->maxLength(10)
                        ->dehydrated(),
                    Forms\Components\Select::make('guest_gender')
                        ->label('Gender')
                        ->required()
                        ->default(fn () => $this->record->guest_gender)
                        ->options([
                            'Male' => 'Male',
                            'Female' => 'Female',
                        ])
                        ->native(false),
                    Forms\Components\Textarea::make('guest_full_address')
                        ->label('Complete Address')
                        ->default(fn () => $this->record->guest_address)
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('guest_contact_number')
                        ->label('Contact Number')
                        ->default(fn () => $this->record->guest_phone)
                        ->required()
                        ->maxLength(30),
                    Forms\Components\TextInput::make('guest_age')
                        ->label('Age')
                        ->default(fn () => $this->record->guest_age)
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120),
                ])->columns(3),

            Forms\Components\Section::make('Room Entries')
                ->description(fn () => $this->record->preferredRoomType->isPrivate()
                    ? 'Add one row per room involved in this check-in. Private reservations are room-level only (no bed assignment).'
                    : 'Add one row per room involved in this check-in. Use PRIVATE for whole-room assignment or DORM for per-bed assignment.')
                ->schema([
                    Forms\Components\Repeater::make('reservation_rooms')
                        ->default(function () {
                            // Pre-populate from advance room holds if they exist
                            $holds = $this->record->roomHolds()
                                ->advance()
                                ->with('room.roomType')
                                ->get();

                            if ($holds->isEmpty()) {
                                return [[
                                    'room_mode' => $this->record->preferredRoomType?->isPrivate() ? 'private' : 'dorm',
                                    'room_id' => null,
                                    'includes_primary_guest' => true,
                                    'guests' => [],
                                ]];
                            }

                            $validEntries = [];
                            $skippedCount = 0;

                            foreach ($holds as $index => $hold) {
                                // Skip if room is deleted or inactive
                                if (! $hold->room || ! $hold->room->is_active) {
                                    $skippedCount++;

                                    continue;
                                }

                                $room = $hold->room;

                                // Skip if room is in maintenance or inactive status
                                if (in_array($room->status, ['maintenance', 'inactive'], true)) {
                                    $skippedCount++;

                                    continue;
                                }

                                $isPrivate = $room->roomType?->isPrivate() ?? false;

                                $validEntries[] = [
                                    'room_mode' => $isPrivate ? 'private' : 'dorm',
                                    'room_id' => $room->id,
                                    'includes_primary_guest' => (count($validEntries) === 0), // Only first gets primary
                                    'guests' => [], // Staff will fill in guest details
                                ];
                            }

                            // Store counts in session for helper text display
                            if ($skippedCount > 0) {
                                session()->flash('room_hold_load_status', [
                                    'total' => $holds->count(),
                                    'loaded' => count($validEntries),
                                    'skipped' => $skippedCount,
                                ]);
                            }

                            return $validEntries;
                        })
                        ->helperText(function () {
                            $status = session('room_hold_load_status');
                            if (! $status) {
                                $totalHolds = $this->record->roomHolds()->advance()->count();
                                if ($totalHolds > 0) {
                                    return "{$totalHolds} room(s) held from approval stage. Pre-populated above.";
                                }

                                return 'Add one or more rooms to proceed with check-in.';
                            }

                            $loaded = $status['loaded'];
                            $skipped = $status['skipped'];
                            $total = $status['total'];

                            if ($skipped > 0) {
                                return "{$loaded}/{$total} held rooms loaded. {$skipped} skipped (inactive/unavailable).";
                            }

                            return "{$loaded} room(s) from approval stage loaded successfully.";
                        })
                        ->schema([
                            Forms\Components\Select::make('room_mode')
                                ->label('Room Mode')
                                ->required()
                                ->options([
                                    'private' => 'Private (occupies whole room)',
                                    'dorm' => 'Dorm (per-bed assignment)',
                                ])
                                ->default(fn () => $this->record->preferredRoomType?->isPrivate() ? 'private' : 'dorm')
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
                                })
                                ->helperText('Choose how to allocate this room to guests'),
                            Forms\Components\Select::make('room_id')
                                ->label('Room')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->options(function ($get) {
                                    $mode = $get('room_mode');
                                    $preferredTypeId = $this->record->preferred_room_type_id;
                                    $cacheKey = $this->record->id.'|'.($mode ?: 'auto').'|'.$preferredTypeId;

                                    static $optionsCache = [];
                                    if (array_key_exists($cacheKey, $optionsCache)) {
                                        return $optionsCache[$cacheKey];
                                    }

                                    // Get rooms already held for this reservation
                                    $heldRooms = $this->record->roomHolds()
                                        ->advance()
                                        ->with('room.roomType')
                                        ->get()
                                        ->pluck('room')
                                        ->filter();

                                    $heldRoomIds = $heldRooms->pluck('id')->toArray();

                                    // If no mode selected yet, try to infer from held room
                                    if (empty($mode) && $heldRooms->isNotEmpty()) {
                                        $firstHeldRoom = $heldRooms->first();
                                        if ($firstHeldRoom && $firstHeldRoom->roomType) {
                                            $mode = $firstHeldRoom->roomType->isPrivate() ? 'private' : 'dorm';
                                        }
                                    }

                                    // Build options array
                                    $options = [];

                                    // Always show held rooms first in a special group
                                    if ($heldRooms->isNotEmpty()) {
                                        $options['Held for this Reservation'] = $heldRooms->mapWithKeys(function ($room) {
                                            return [$room->id => "Room {$room->room_number} ({$room->roomType->name})"];
                                        })->toArray();
                                    }

                                    // If we have a valid mode, show other available rooms
                                    if (in_array($mode, ['private', 'dorm'], true)) {
                                        $query = Room::query()
                                            ->with('roomType')
                                            ->where('is_active', true)
                                            ->whereNotIn('id', $heldRoomIds) // Exclude held rooms (already shown above)
                                            ->whereHas('roomType', function ($q) use ($mode) {
                                                if ($mode === 'private') {
                                                    $q->where('room_sharing_type', 'private');
                                                } else {
                                                    $q->where('room_sharing_type', '!=', 'private');
                                                }
                                            });

                                        if ($mode === 'dorm') {
                                            $query->whereIn('status', ['available', 'occupied'])
                                                ->whereRaw('capacity > (
                                                    SELECT COUNT(*) FROM room_assignments
                                                    WHERE room_assignments.room_id = rooms.id
                                                    AND room_assignments.status = ?
                                                )', ['checked_in']);
                                        } else {
                                            $query->where('status', 'available');
                                        }

                                        $availableRooms = $query->get();

                                        if ($availableRooms->isNotEmpty()) {
                                            $grouped = $availableRooms->groupBy('room_type_id')->sortBy(function ($group, $typeId) use ($preferredTypeId) {
                                                return $typeId == $preferredTypeId ? 0 : 1;
                                            });

                                            foreach ($grouped as $typeId => $roomsInType) {
                                                $typeName = $roomsInType->first()->roomType->name;
                                                $isPreferred = $typeId == $preferredTypeId;
                                                $groupLabel = $isPreferred ? "{$typeName} (Preferred)" : $typeName;

                                                $options[$groupLabel] = $roomsInType->mapWithKeys(function ($room) {
                                                    return [$room->id => "Room {$room->room_number}"];
                                                })->toArray();
                                            }
                                        }
                                    }

                                    // If no options available at all
                                    if (empty($options)) {
                                        return $optionsCache[$cacheKey] = ['' => '(No rooms available - select mode first)'];
                                    }

                                    return $optionsCache[$cacheKey] = $options;
                                })
                                ->live()
                                ->helperText(fn ($get) => filled($get('room_mode') ?? null)
                                    ? 'Held rooms shown first. Preferred room type shown in other available rooms.'
                                    : 'Room mode is pre-filled based on held room'),
                            Forms\Components\Toggle::make('includes_primary_guest')
                                ->label('Primary guest stays in this room')
                                ->helperText(fn ($get) => filled($get('room_id') ?? null)
                                    ? 'The primary guest can be assigned to one room only. Enable this on the room where they will stay.'
                                    : 'Select a room first. The primary guest can be assigned to one room only.')
                                ->default(false)
                                ->inline(false)
                                ->live()
                                ->afterStateUpdated(function (bool $state, Forms\Components\Toggle $component): void {
                                    if ($state) {
                                        $this->keepOnlyPrimaryGuestRoom($component->getStatePath());
                                    }
                                })
                                ->disabled(fn ($get) => blank($get('room_id') ?? null))
                                ->dehydrated(),
                            Forms\Components\Repeater::make('guests')
                                ->label('Companion Guests')
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
                                        ->options([
                                            'Male' => 'Male',
                                            'Female' => 'Female',
                                        ])
                                        ->native(false),
                                ])
                                ->columns(5)
                                ->minItems(0)
                                ->defaultItems(0)
                                ->addActionLabel('Add Another Guest')
                                ->helperText('Add companion guests only. Primary guest is auto-included when enabled above.')
                                ->visible(fn ($get) => filled($get('room_mode') ?? null) && filled($get('room_id') ?? null))
                                ->reorderable(false),
                        ])
                        ->defaultItems(1)
                        ->minItems(1)
                        ->reorderable(false)
                        ->columnSpanFull()
                        ->addActionLabel('Add Another Room'),
                ]),

            Forms\Components\Section::make('Identification & Status')
                ->schema([
                    Forms\Components\Select::make('id_type')
                        ->label('ID Type')
                        ->required()
                        ->options([
                            'National ID' => 'National ID',
                            "Driver's License" => "Driver's License",
                            'Passport' => 'Passport',
                            'Student ID' => 'Student ID',
                            'SSS ID' => 'SSS ID',
                            'UMID' => 'UMID',
                            'Phil Health ID' => 'Phil Health ID',
                            "Voter's ID" => "Voter's ID",
                            'Senior Citizen ID' => 'Senior Citizen ID',
                            'PWD ID' => 'PWD ID',
                            'Other' => 'Other',
                        ])
                        ->searchable(),
                    Forms\Components\TextInput::make('id_number')
                        ->label('ID Number')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\Select::make('nationality')
                        ->label('Nationality')
                        ->default('Filipino')
                        ->required()
                        ->searchable()
                        ->options($this->getNationalitiesOptions()),
                    Forms\Components\Toggle::make('is_student')
                        ->label('Student')
                        ->inline(false)
                        ->live(),
                    Forms\Components\Toggle::make('is_senior_citizen')
                        ->label('Senior Citizen')
                        ->inline(false)
                        ->live(),
                    Forms\Components\Toggle::make('is_pwd')
                        ->label('PWD')
                        ->inline(false)
                        ->live(),
                ])->columns(3),

            Forms\Components\Section::make('Stay Details')
                ->schema([
                    Forms\Components\Select::make('purpose_of_stay')
                        ->label('Purpose of Stay')
                        ->default(function () {
                            $map = [
                                'academic' => 'Academic',
                                'official' => 'Official Business',
                                'personal' => 'Personal',
                                'event' => 'Event/Conference',
                                'training' => 'Training',
                                'research' => 'Research',
                                'other' => 'Other',
                            ];

                            return $map[$this->record->purpose]
                                ?? ucwords(str_replace('_', ' ', $this->record->purpose ?? 'Personal'));
                        })
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
                    Forms\Components\Hidden::make('num_male_guests')->default(0),
                    Forms\Components\Hidden::make('num_female_guests')->default(0),
                ])->columns(1),

            Forms\Components\Section::make('Check-in / Check-out Schedule')
                ->schema([
                    Forms\Components\DatePicker::make('detailed_checkin_datetime')
                        ->label('Date of Arrival')
                        ->default(fn () => $this->record->check_in_date->toDateString())
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('detailed_checkout_datetime')
                        ->label('Scheduled Check-out Date')
                        ->default(fn () => $this->record->check_out_date->toDateString())
                        ->required()
                        ->native(false)
                        ->after('detailed_checkin_datetime'),
                ])->columns(2),

            Forms\Components\Section::make('Add-Ons & Estimated Charges')
                ->schema([
                    Forms\Components\Repeater::make('additional_requests')
                        ->label('Add-Ons')
                        ->schema([
                            Forms\Components\Select::make('code')
                                ->label('Add-On')
                                ->options(fn () => Service::active()->ordered()->get()
                                    ->mapWithKeys(fn (Service $s) => [
                                        $s->code => $s->name.($s->price > 0 ? " ({$s->formatted_price})" : ' (Free)'),
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
                        ->helperText('Selected add-ons are included in the estimated payable amount below.')
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('declared_occupants')
                        ->label('Declared Number of Guests')
                        ->content(fn () => $this->record->number_of_occupants.' guest'.($this->record->number_of_occupants > 1 ? 's' : '')),
                    Forms\Components\Placeholder::make('declared_days')
                        ->label('Declared Number of Nights')
                        ->content(function ($get) {
                            $checkIn = $get('detailed_checkin_datetime');
                            $checkOut = $get('detailed_checkout_datetime');

                            if ($checkIn && $checkOut) {
                                $d = max(1, \Carbon\Carbon::parse($checkIn)->startOfDay()->diffInDays(\Carbon\Carbon::parse($checkOut)->startOfDay()));
                            } else {
                                $d = max(1, $this->record->check_in_date->startOfDay()->diffInDays($this->record->check_out_date->startOfDay()));
                            }

                            return $d.' night'.($d > 1 ? 's' : '');
                        }),
                    Forms\Components\Placeholder::make('live_checkin_pricing_breakdown')
                        ->label('Estimated Payable Amount (Actual Check-in Data)')
                        ->content(function ($get) {
                            $pricing = ReservationResource::computeCheckInPricing(
                                $this->record,
                                $get('reservation_rooms') ?? [],
                                $get('detailed_checkin_datetime'),
                                $get('detailed_checkout_datetime'),
                                $get('additional_requests') ?? [],
                                $get('is_pwd') ?? false,
                                $get('is_senior_citizen') ?? false,
                                $get('is_student') ?? false
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
                            $html .= '<div><strong>Nights:</strong> '.$pricing['nights'].'</div>';
                            $html .= '<ul class="list-disc pl-5 space-y-1">'.implode('', $rows).'</ul>';
                            $html .= '<div><strong>Room Subtotal:</strong> PHP '.number_format($pricing['room_subtotal'], 2).'</div>';
                            $html .= '<div><strong>Add-Ons:</strong> PHP '.number_format($pricing['services_total'], 2).'</div>';
                            $html .= '<div><strong>Subtotal:</strong> PHP '.number_format($pricing['subtotal'], 2).'</div>';

                            if ($pricing['discount_amount'] > 0) {
                                $html .= '<div class="text-green-600"><strong>Discount ('.$pricing['discount_percent'].'%):</strong> -PHP '.number_format($pricing['discount_amount'], 2).'</div>';
                            }

                            $html .= '<div class="font-semibold"><strong>Estimated Payable:</strong> PHP '.number_format($pricing['grand_total'], 2).'</div>';

                            // Show deposit deduction if any posted payments exist
                            $existingPayments = (float) $this->record->payments()
                                ->where('status', 'posted')
                                ->sum('amount');

                            if ($existingPayments > 0) {
                                $remaining = max(0, $pricing['grand_total'] - $existingPayments);
                                $html .= '<div class="text-blue-600 mt-1"><strong>Less: Prior Payment(s):</strong> -PHP '.number_format($existingPayments, 2).'</div>';
                                $html .= '<div class="font-bold text-lg mt-1"><strong>Remaining Balance:</strong> PHP '.number_format($remaining, 2).'</div>';
                            }

                            $html .= '</div>';

                            return new HtmlString($html);
                        })
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('remarks')
                        ->label('Check-in Remarks')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Payment Details')
                ->schema([
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
                    Forms\Components\TextInput::make('payment_amount')
                        ->label('Paid Amount')
                        ->numeric()
                        ->prefix('PHP')
                        ->default(function ($get) {
                            $pricing = ReservationResource::computeCheckInPricing(
                                $this->record,
                                $get('reservation_rooms') ?? [],
                                $get('detailed_checkin_datetime'),
                                $get('detailed_checkout_datetime'),
                                $get('additional_requests') ?? [],
                                $get('is_pwd') ?? false,
                                $get('is_senior_citizen') ?? false,
                                $get('is_student') ?? false
                            );

                            $existingPayments = (float) $this->record->payments()
                                ->where('status', 'posted')
                                ->sum('amount');

                            return round(max(0, $pricing['grand_total'] - $existingPayments), 2);
                        })
                        ->helperText('Enter the amount collected at reception. The system will reject amounts below the payable balance.')
                        ->required(),
                    Forms\Components\TextInput::make('payment_or_number')
                        ->label('Official Receipt Number')
                        ->maxLength(100)
                        ->required(),
                    Forms\Components\DatePicker::make('or_date')
                        ->label('OR Date')
                        ->displayFormat('M d, Y')
                        ->default(now()->toDateString())
                        ->required()
                        ->helperText('Date on the official receipt'),
                ])->columns(2),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        try {
            $this->validateSinglePrimaryGuestRoom($data);

            $result = app(CheckInService::class)->completeOnsiteCheckIn($this->record, $data);

            if (($result['all_succeeded'] ?? false) === true) {
                Notification::make()
                    ->success()
                    ->title('Reservation Checked In')
                    ->body("Checked in {$result['checked_in_count']} guest(s) and recorded onsite payment.")
                    ->send();

                $this->redirect(ReservationResource::getUrl('index'));

                return;
            }

            $messages = array_merge(
                $result['room_errors'] ?? [],
                $result['failed_guests'] ?? []
            );

            Notification::make()
                ->warning()
                ->title('Check-in Completed With Issues')
                ->body(implode(' ', array_slice($messages, 0, 5)))
                ->persistent()
                ->send();

            $this->redirect(ReservationResource::getUrl('index'));
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Unable to Check In')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::SevenExtraLarge;
    }

    public function keepOnlyPrimaryGuestRoom(string $statePath): void
    {
        $segments = explode('.', $statePath);
        $fieldIndex = array_search('includes_primary_guest', $segments, true);
        $roomKey = $fieldIndex !== false ? ($segments[$fieldIndex - 1] ?? null) : null;

        if ($roomKey === null || ! isset($this->data['reservation_rooms']) || ! is_array($this->data['reservation_rooms'])) {
            return;
        }

        foreach ($this->data['reservation_rooms'] as $key => $room) {
            $this->data['reservation_rooms'][$key]['includes_primary_guest'] = ((string) $key === (string) $roomKey);
        }
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function validateSinglePrimaryGuestRoom(array $data): void
    {
        $selectedCount = collect($data['reservation_rooms'] ?? [])
            ->filter(fn ($entry) => (bool) ($entry['includes_primary_guest'] ?? false))
            ->count();

        if ($selectedCount !== 1) {
            throw new \RuntimeException('Choose exactly one room where the primary guest will stay.');
        }
    }

    protected function getNationalitiesOptions(): array
    {
        return [
            'Afghan' => 'Afghan',
            'Albanian' => 'Albanian',
            'Algerian' => 'Algerian',
            'American' => 'American',
            'Argentinian' => 'Argentinian',
            'Australian' => 'Australian',
            'Austrian' => 'Austrian',
            'Bangladeshi' => 'Bangladeshi',
            'Belgian' => 'Belgian',
            'Bolivian' => 'Bolivian',
            'Brazilian' => 'Brazilian',
            'British' => 'British',
            'Bruneian' => 'Bruneian',
            'Bulgarian' => 'Bulgarian',
            'Cambodian' => 'Cambodian',
            'Cameroonian' => 'Cameroonian',
            'Canadian' => 'Canadian',
            'Chilean' => 'Chilean',
            'Chinese' => 'Chinese',
            'Colombian' => 'Colombian',
            'Costa Rican' => 'Costa Rican',
            'Croatian' => 'Croatian',
            'Cuban' => 'Cuban',
            'Czech' => 'Czech',
            'Danish' => 'Danish',
            'Dominican' => 'Dominican',
            'Dutch' => 'Dutch',
            'Ecuadorian' => 'Ecuadorian',
            'Egyptian' => 'Egyptian',
            'Emirati' => 'Emirati',
            'English' => 'English',
            'Estonian' => 'Estonian',
            'Ethiopian' => 'Ethiopian',
            'Fijian' => 'Fijian',
            'Filipino' => 'Filipino',
            'Finnish' => 'Finnish',
            'French' => 'French',
            'German' => 'German',
            'Ghanaian' => 'Ghanaian',
            'Greek' => 'Greek',
            'Guatemalan' => 'Guatemalan',
            'Haitian' => 'Haitian',
            'Honduran' => 'Honduran',
            'Hungarian' => 'Hungarian',
            'Icelandic' => 'Icelandic',
            'Indian' => 'Indian',
            'Indonesian' => 'Indonesian',
            'Iranian' => 'Iranian',
            'Iraqi' => 'Iraqi',
            'Irish' => 'Irish',
            'Israeli' => 'Israeli',
            'Italian' => 'Italian',
            'Jamaican' => 'Jamaican',
            'Japanese' => 'Japanese',
            'Jordanian' => 'Jordanian',
            'Kazakh' => 'Kazakh',
            'Kenyan' => 'Kenyan',
            'Korean' => 'Korean',
            'Kuwaiti' => 'Kuwaiti',
            'Lao' => 'Lao',
            'Latvian' => 'Latvian',
            'Lebanese' => 'Lebanese',
            'Libyan' => 'Libyan',
            'Lithuanian' => 'Lithuanian',
            'Malaysian' => 'Malaysian',
            'Mexican' => 'Mexican',
            'Mongolian' => 'Mongolian',
            'Moroccan' => 'Moroccan',
            'Mozambican' => 'Mozambican',
            'Myanmar' => 'Myanmar',
            'Namibian' => 'Namibian',
            'Nepalese' => 'Nepalese',
            'New Zealander' => 'New Zealander',
            'Nicaraguan' => 'Nicaraguan',
            'Nigerian' => 'Nigerian',
            'Norwegian' => 'Norwegian',
            'Omani' => 'Omani',
            'Pakistani' => 'Pakistani',
            'Palestinian' => 'Palestinian',
            'Panamanian' => 'Panamanian',
            'Papua New Guinean' => 'Papua New Guinean',
            'Paraguayan' => 'Paraguayan',
            'Peruvian' => 'Peruvian',
            'Polish' => 'Polish',
            'Portuguese' => 'Portuguese',
            'Qatari' => 'Qatari',
            'Romanian' => 'Romanian',
            'Russian' => 'Russian',
            'Rwandan' => 'Rwandan',
            'Saudi' => 'Saudi',
            'Scottish' => 'Scottish',
            'Senegalese' => 'Senegalese',
            'Serbian' => 'Serbian',
            'Singaporean' => 'Singaporean',
            'Slovak' => 'Slovak',
            'Slovenian' => 'Slovenian',
            'South African' => 'South African',
            'Spanish' => 'Spanish',
            'Sri Lankan' => 'Sri Lankan',
            'Sudanese' => 'Sudanese',
            'Swedish' => 'Swedish',
            'Swiss' => 'Swiss',
            'Syrian' => 'Syrian',
            'Taiwanese' => 'Taiwanese',
            'Tanzanian' => 'Tanzanian',
            'Thai' => 'Thai',
            'Timorese' => 'Timorese',
            'Trinidadian' => 'Trinidadian',
            'Tunisian' => 'Tunisian',
            'Turkish' => 'Turkish',
            'Ugandan' => 'Ugandan',
            'Ukrainian' => 'Ukrainian',
            'Uruguayan' => 'Uruguayan',
            'Uzbek' => 'Uzbek',
            'Venezuelan' => 'Venezuelan',
            'Vietnamese' => 'Vietnamese',
            'Welsh' => 'Welsh',
            'Yemeni' => 'Yemeni',
            'Zambian' => 'Zambian',
            'Zimbabwean' => 'Zimbabwean',
        ];
    }
}
