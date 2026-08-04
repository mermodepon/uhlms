<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\Service;
use App\Models\Setting;
use App\Services\CheckInService;
use App\Services\PaymentGatewayService;
use App\Support\PayMongoPaymentMetadata;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CheckInGuest extends Page
{
    protected static string $resource = ReservationResource::class;

    protected static string $view = 'filament.resources.reservation-resource.pages.check-in-guest';

    protected static ?string $title = 'Check In Guest';

    public ?array $data = [];

    public Reservation $record;

    public ?array $roomHoldLoadStatus = null;

    public function mount(Reservation $record): void
    {
        $this->record = $record;

        abort_unless(in_array($record->status, ['approved', 'confirmed']), 403, 'This reservation cannot be checked in.');

        $this->form->fill();
        $initialRoomEntries = $this->buildInitialReservationRoomEntries();
        $this->form->fill(array_merge($this->data ?? [], [
            'reservation_rooms' => $initialRoomEntries,
        ]));

        // Defaults are evaluated before the held-room entries are populated.
        // Set the cash field after those entries are loaded so reception starts
        // with the actual live amount still due, net of posted payments.
        $this->form->fill(array_merge($this->data ?? [], [
            'payment_amount' => $this->getRemainingBalance(),
        ]));
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
                        ->options(fn () => $this->getGenderOptions())
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
                        ->default(fn () => [$this->blankReservationRoomEntry()])
                        ->helperText(function () {
                            $status = $this->roomHoldLoadStatus;
                            if (! $status) {
                                $totalHolds = $this->record->roomHolds()->advance()->active()->count();
                                if ($totalHolds > 0) {
                                    return "{$totalHolds} room(s) held from approval stage.";
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
                            Forms\Components\Hidden::make('expected_guest_count')
                                ->default(1)
                                ->dehydrated(),
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
                                        ->options(fn () => $this->getGenderOptions())
                                        ->native(false),
                                ])
                                ->columns(5)
                                ->minItems(0)
                                ->defaultItems(0)
                                ->addActionLabel('Add Another Guest')
                                ->helperText(function ($get) {
                                    $expected = max(0, (int) ($get('expected_guest_count') ?? 0));
                                    if ($expected > 0) {
                                        return "Expected {$expected} occupant".($expected === 1 ? '' : 's').' in this room. Add companion guests only. Primary guest is auto-included when enabled above.';
                                    }

                                    return 'Add companion guests only. Primary guest is auto-included when enabled above.';
                                })
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
                        ->content(function ($get) {
                            $declared = max(1, (int) ($this->record->number_of_occupants ?? 1));
                            $allocated = $this->countAllocatedGuests($get('reservation_rooms') ?? []);
                            $text = $declared.' guest'.($declared === 1 ? '' : 's');

                            if ($allocated !== $declared) {
                                $text .= " ({$allocated} currently allocated in room entries)";
                            }

                            return $text;
                        }),
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

            Forms\Components\Section::make('Online Payments')
                ->visible(fn () => Setting::isOnlinePaymentsEnabled() || $this->record->payments()->where('gateway', 'paymongo')->exists())
                ->schema([
                    Forms\Components\Placeholder::make('online_payment_status')
                        ->label('')
                        ->content(fn () => $this->renderOnlinePaymentPanel())
                        ->columnSpanFull(),
                ]),

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
                        ->disabled(fn () => $this->shouldDisableManualPaymentFields())
                        ->required(fn () => ! $this->shouldDisableManualPaymentFields()),
                    Forms\Components\TextInput::make('payment_mode_other')
                        ->label('Specify Payment Mode')
                        ->visible(fn ($get) => $get('payment_mode') === 'others')
                        ->disabled(fn () => $this->shouldDisableManualPaymentFields())
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
                        ->disabled(fn () => $this->shouldDisableManualPaymentFields())
                        ->required(fn () => ! $this->shouldDisableManualPaymentFields()),
                    Forms\Components\TextInput::make('payment_or_number')
                        ->label('Official Receipt Number')
                        ->maxLength(100)
                        ->disabled(fn () => $this->shouldDisableManualPaymentFields())
                        ->required(fn () => ! $this->shouldDisableManualPaymentFields()),
                    Forms\Components\DatePicker::make('or_date')
                        ->label('OR Date')
                        ->displayFormat('M d, Y')
                        ->default(now()->toDateString())
                        ->disabled(fn () => $this->shouldDisableManualPaymentFields())
                        ->required(fn () => ! $this->shouldDisableManualPaymentFields())
                        ->helperText('Date on the official receipt'),
                ])->columns(2),
        ];
    }

    protected function buildInitialReservationRoomEntries(): array
    {
        $holds = $this->record->roomHolds()
            ->advance()
            ->active()
            ->with('room.roomType')
            ->get();

        if ($holds->isEmpty()) {
            $this->roomHoldLoadStatus = null;

            return [$this->blankReservationRoomEntry()];
        }

        $entries = [];
        $skippedCount = 0;
        $validHolds = collect();

        foreach ($holds as $hold) {
            $room = $hold->room;

            if (! $room || ! $room->is_active || in_array($room->status, ['maintenance', 'inactive'], true)) {
                $skippedCount++;

                continue;
            }

            $validHolds->push($hold);
        }

        $allocations = $this->allocateExpectedGuestCounts($validHolds);
        $primaryHoldIndex = $this->resolvePrimaryHoldIndex($validHolds);

        foreach ($validHolds->values() as $index => $hold) {
            $room = $hold->room;
            $expectedGuestCount = max(0, (int) ($allocations[$index] ?? 0));
            $includesPrimaryGuest = $index === $primaryHoldIndex && $expectedGuestCount > 0;
            $companionCount = max(0, $expectedGuestCount - ($includesPrimaryGuest ? 1 : 0));

            $entries[] = [
                'room_mode' => ($room->roomType?->isPrivate() ?? false) ? 'private' : 'dorm',
                'room_id' => $room->id,
                'expected_guest_count' => $expectedGuestCount,
                'includes_primary_guest' => $includesPrimaryGuest,
                'guests' => $this->blankCompanionGuestRows($companionCount),
            ];
        }

        $this->roomHoldLoadStatus = [
            'total' => $holds->count(),
            'loaded' => count($entries),
            'skipped' => $skippedCount,
        ];

        return ! empty($entries)
            ? $entries
            : [$this->blankReservationRoomEntry()];
    }

    protected function blankReservationRoomEntry(): array
    {
        return [
            'room_mode' => $this->record->preferredRoomType?->isPrivate() ? 'private' : 'dorm',
            'room_id' => null,
            'expected_guest_count' => 1,
            'includes_primary_guest' => true,
            'guests' => [],
        ];
    }

    protected function allocateExpectedGuestCounts($holds): array
    {
        $holds = $holds->values();
        $declaredGuests = max(1, (int) ($this->record->number_of_occupants ?? 1));

        if ($holds->isEmpty()) {
            return [];
        }

        $allocations = array_fill(0, $holds->count(), 0);
        $requests = $this->record->getEffectiveRoomRequests()->values();

        foreach ($holds as $index => $hold) {
            $room = $hold->room;
            $isDorm = ! ($room->roomType?->isPrivate() ?? false);

            if ($isDorm && $hold->held_guest_count) {
                $allocations[$index] = max(1, min((int) $hold->held_guest_count, $this->roomCapacity($room)));
            }
        }

        foreach ($requests as $request) {
            $roomTypeId = (int) $request->room_type_id;
            $matchingIndexes = $holds
                ->keys()
                ->filter(fn ($index) => (int) ($holds[$index]->room?->room_type_id ?? 0) === $roomTypeId)
                ->values();
            $remaining = max(
                0,
                (int) $request->occupant_count - $matchingIndexes->sum(fn ($index) => (int) ($allocations[$index] ?? 0))
            );

            if ($remaining <= 0 || $matchingIndexes->isEmpty()) {
                continue;
            }

            foreach ($matchingIndexes as $index) {
                if ($remaining <= 0) {
                    break;
                }

                $hold = $holds[$index];
                $room = $hold->room;
                $isDorm = ! ($room->roomType?->isPrivate() ?? false);

                if ($isDorm && $hold->held_guest_count) {
                    $slots = max(1, min($remaining, (int) $hold->held_guest_count));
                } else {
                    $slots = max(1, min($remaining, $this->roomCapacity($room)));
                }

                $allocations[$index] += $slots;
                $remaining -= $slots;
            }
        }

        $allocated = array_sum($allocations);

        if ($allocated === 0) {
            $allocations[$this->resolvePrimaryHoldIndex($holds)] = min($declaredGuests, $this->roomCapacity($holds->first()->room));
            $allocated = array_sum($allocations);
        }

        while ($allocated < $declaredGuests) {
            $changed = false;

            foreach ($holds as $index => $hold) {
                if ($allocated >= $declaredGuests) {
                    break;
                }

                $capacity = $this->roomCapacity($hold->room);
                if ($allocations[$index] >= $capacity) {
                    continue;
                }

                $allocations[$index]++;
                $allocated++;
                $changed = true;
            }

            if (! $changed) {
                $allocations[$this->resolvePrimaryHoldIndex($holds)] += $declaredGuests - $allocated;
                $allocated = $declaredGuests;
            }
        }

        while ($allocated > $declaredGuests) {
            $changed = false;

            for ($index = count($allocations) - 1; $index >= 0; $index--) {
                if ($allocated <= $declaredGuests) {
                    break;
                }

                if ($allocations[$index] <= 0) {
                    continue;
                }

                $allocations[$index]--;
                $allocated--;
                $changed = true;
            }

            if (! $changed) {
                break;
            }
        }

        return $allocations;
    }

    protected function resolvePrimaryHoldIndex($holds): int
    {
        $holds = $holds->values();
        $primaryTypeId = (int) ($this->record->getEffectiveRoomRequests()->first()?->room_type_id ?? $this->record->preferred_room_type_id);

        if ($primaryTypeId) {
            foreach ($holds as $index => $hold) {
                if ((int) ($hold->room?->room_type_id ?? 0) === $primaryTypeId) {
                    return (int) $index;
                }
            }
        }

        return 0;
    }

    protected function roomCapacity(?Room $room): int
    {
        return max(1, (int) ($room?->capacity ?? 1));
    }

    protected function blankCompanionGuestRows(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        return collect(range(1, $count))
            ->map(fn () => [
                'last_name' => null,
                'first_name' => null,
                'middle_initial' => null,
                'age' => null,
                'gender' => null,
            ])
            ->all();
    }

    protected function countAllocatedGuests(array $reservationRooms): int
    {
        return collect($reservationRooms)->sum(function (array $entry): int {
            return count($entry['guests'] ?? [])
                + ((bool) ($entry['includes_primary_guest'] ?? false) ? 1 : 0);
        });
    }

    public function createPayMongoBalanceCheckout(): void
    {
        $this->record->refresh();
        app(\App\Services\ReservationAccountLinker::class)->link($this->record);

        if (! Setting::isOnlinePaymentsEnabled()) {
            Notification::make()
                ->danger()
                ->title('Online payments are disabled.')
                ->send();

            return;
        }

        if (! in_array($this->record->status, ['approved', 'confirmed'], true)) {
            Notification::make()
                ->danger()
                ->title('This reservation cannot collect online check-in balance.')
                ->send();

            return;
        }

        if ($this->getPendingCheckInBalancePayment()) {
            Notification::make()
                ->warning()
                ->title('A PayMongo balance checkout is already pending.')
                ->send();

            return;
        }

        $amount = $this->getRemainingBalance();
        if ($amount <= 0.01) {
            Notification::make()
                ->success()
                ->title('No remaining balance to collect.')
                ->send();

            return;
        }

        try {
            $guestResultToken = (string) Str::uuid();
            $guestResultPath = route('guest.check-in-payment.result', ['token' => $guestResultToken], false);
            $checkoutSession = app(PaymentGatewayService::class)->createCheckoutSession(
                $this->record,
                $amount,
                'checkin_balance',
                null,
                [
                    'success' => $this->absoluteUrl($guestResultPath),
                    'cancel' => $this->absoluteUrl($guestResultPath.'?cancelled=1'),
                ]
            );

            ReservationPayment::create([
                'reservation_id' => $this->record->id,
                'amount' => $amount,
                'payment_mode' => 'paymongo_online',
                'gateway' => 'paymongo',
                'gateway_payment_id' => $checkoutSession['payment_intent_id'],
                'gateway_source_id' => $checkoutSession['checkout_session_id'],
                'gateway_status' => 'pending',
                'is_deposit' => false,
                'status' => 'pending',
                'gateway_metadata' => PayMongoPaymentMetadata::sanitize([
                    'checkout_session_created_at' => now()->toIso8601String(),
                    'checkout_session_id' => $checkoutSession['checkout_session_id'] ?? null,
                    'checkout_url' => $checkoutSession['checkout_url'] ?? null,
                    'checkout_payment_methods' => $checkoutSession['payment_method_types'] ?? [],
                    'payment_type' => 'checkin_balance',
                    'guest_result_token' => $guestResultToken,
                    'reservation_id' => (string) $this->record->id,
                    'reservation_ref' => (string) $this->record->reference_number,
                ], 'pending'),
                'meta' => [
                    'source' => 'checkin_balance',
                    'payment_type' => 'checkin_balance',
                    // Keep the guest return token outside gateway metadata too.
                    // Long-running webhook workers sanitize gateway metadata on
                    // status updates, while this application-owned metadata is preserved.
                    'guest_result_token' => $guestResultToken,
                ],
            ]);

            ReservationLog::record(
                $this->record,
                'checkin_balance_payment_initiated',
                'Staff generated PayMongo Checkout for check-in balance of PHP '.number_format($amount, 2).'.',
                [
                    'amount' => $amount,
                    'checkout_session_id' => $checkoutSession['checkout_session_id'] ?? null,
                    'gateway_payment_id' => $checkoutSession['payment_intent_id'] ?? null,
                ]
            );

            $this->record->refresh();

            Notification::make()
                ->success()
                ->title('PayMongo checkout generated.')
                ->body('Ask the guest to scan the QR code or open the checkout link below.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Unable to generate PayMongo checkout.')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function cancelPendingPayMongoBalanceCheckout(): void
    {
        $this->record->refresh();
        $pendingPayment = $this->getPendingCheckInBalancePayment();

        if (! $pendingPayment) {
            Notification::make()
                ->warning()
                ->title('No pending PayMongo request to cancel.')
                ->send();

            return;
        }

        $pendingPayment->update([
            'gateway_status' => 'cancelled',
            'status' => 'cancelled',
            'gateway_metadata' => PayMongoPaymentMetadata::sanitize(
                array_merge($pendingPayment->gateway_metadata ?? [], [
                    'cancelled_at' => now()->toIso8601String(),
                    'cancelled_by' => auth()->id(),
                    'cancellation_source' => 'checkin_staff_action',
                    'cancellation_reason' => 'Guest will pay manually at reception.',
                ]),
                'cancelled',
            ),
            'meta' => array_merge(
                $pendingPayment->meta ?? [],
                [
                    'cancelled_at' => now()->toIso8601String(),
                    'cancelled_by' => auth()->id(),
                ]
            ),
        ]);

        ReservationLog::record(
            $this->record,
            'checkin_balance_payment_cancelled',
            'Staff cancelled pending PayMongo Checkout for check-in balance of PHP '.number_format((float) $pendingPayment->amount, 2).'.',
            [
                'payment_id' => $pendingPayment->id,
                'amount' => (float) $pendingPayment->amount,
                'gateway_payment_id' => $pendingPayment->gateway_payment_id,
                'checkout_session_id' => $pendingPayment->gateway_source_id,
                'cancelled_by' => auth()->id(),
            ]
        );

        $this->record->refresh();

        Notification::make()
            ->success()
            ->title('PayMongo request cancelled.')
            ->body('Manual payment fields are available again.')
            ->send();
    }

    protected function renderOnlinePaymentPanel(): HtmlString
    {
        $summary = $this->getOnlinePaymentSummary();
        $pendingPayment = $this->getPendingCheckInBalancePayment();
        $checkoutUrl = $pendingPayment ? data_get($pendingPayment->gateway_metadata, 'checkout_url') : null;

        $rows = [
            ['Prior PayMongo deposit', $summary['deposit_paid']],
            ['Paid PayMongo balance', $summary['balance_paid']],
            ['Pending PayMongo balance', $summary['balance_pending']],
            ['Remaining balance', $summary['remaining_balance']],
        ];

        $html = '<div class="space-y-4" wire:poll.8s>';
        $html .= '<div class="grid gap-2 text-sm">';
        foreach ($rows as [$label, $amount]) {
            $emphasis = $label === 'Remaining balance' ? 'font-bold text-base' : '';
            $html .= '<div class="flex items-center justify-between gap-4 '.$emphasis.'">';
            $html .= '<span>'.e($label).'</span>';
            $html .= '<span>PHP '.number_format((float) $amount, 2).'</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($summary['remaining_balance'] <= 0.01) {
            $paidBalancePayment = $this->getPaidCheckInBalancePayment();
            $onlineReference = $paidBalancePayment?->reference_no
                ?: ($paidBalancePayment?->gateway_payment_id ? 'PM-'.$paidBalancePayment->gateway_payment_id : null);
            $paymentDate = $paidBalancePayment?->or_date
                ? $paidBalancePayment->or_date->format('M d, Y')
                : $paidBalancePayment?->received_at?->format('M d, Y');
            $officialOrNumber = $this->getOfficialReceiptNumber();

            $html .= '<div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">';
            $html .= '<strong>Remaining balance paid online.</strong> You can complete check-in without collecting another payment.';
            $html .= '</div>';
            if ($paidBalancePayment) {
                $html .= '<div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-800">';
                $html .= '<div class="grid gap-2 sm:grid-cols-2">';
                $html .= '<div><span class="font-semibold">Payment Method:</span> '.e($paidBalancePayment->payment_mode ?: 'PayMongo Online').'</div>';
                $html .= '<div><span class="font-semibold">Paid Amount:</span> PHP '.number_format((float) $paidBalancePayment->amount, 2).'</div>';
                $html .= '<div><span class="font-semibold">Online Payment Reference:</span> '.e($onlineReference ?: 'Not available').'</div>';
                $html .= '<div><span class="font-semibold">Payment Date:</span> '.e($paymentDate ?: 'Not available').'</div>';
                $html .= '<div><span class="font-semibold">Official OR Number:</span> '.e($officialOrNumber ?: 'Not yet encoded').'</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
        } elseif ($pendingPayment) {
            $html .= '<div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">';
            $html .= '<p class="font-semibold">Waiting for PayMongo confirmation.</p>';
            $html .= '<p class="mt-1">Check-in remains blocked until PayMongo confirms payment.</p>';
            if ($checkoutUrl) {
                $qrUrl = route('admin.qr-code', ['payload' => Crypt::encryptString($checkoutUrl)]);
                $html .= '<div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">';
                $html .= '<img src="'.e($qrUrl).'" alt="PayMongo checkout QR code" class="h-40 w-40 rounded-lg border bg-white p-2">';
                $html .= '<div class="min-w-0 flex-1">';
                $html .= '<p class="break-all rounded border bg-white p-2 text-xs text-gray-700">'.e($checkoutUrl).'</p>';
                $html .= '<div class="mt-3 flex flex-wrap gap-2">';
                $html .= '<a href="'.e($checkoutUrl).'" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">Open checkout</a>';
                $html .= '<button type="button" x-data="{}" x-on:click="navigator.clipboard.writeText('.e(json_encode($checkoutUrl)).')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Copy link</button>';
                $html .= '</div></div></div>';
            }
            $html .= '<div class="mt-4 border-t border-amber-200 pt-3">';
            $html .= '<button type="button" wire:click="cancelPendingPayMongoBalanceCheckout" wire:confirm="Cancel this pending PayMongo request and allow manual payment instead?" wire:loading.attr="disabled" wire:target="cancelPendingPayMongoBalanceCheckout" class="inline-flex items-center rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-70">';
            $html .= '<span wire:loading.remove wire:target="cancelPendingPayMongoBalanceCheckout">Cancel PayMongo Request</span>';
            $html .= '<span wire:loading wire:target="cancelPendingPayMongoBalanceCheckout">Cancelling...</span>';
            $html .= '</button>';
            $html .= '<p class="mt-2 text-xs text-amber-800">Use this if the guest decides to pay manually. If they later pay this PayMongo link, the system will still record the real payment for reconciliation.</p>';
            $html .= '</div>';
            $html .= '</div>';
        } elseif ($this->canCreatePayMongoBalanceCheckout()) {
            $html .= '<button type="button" wire:click="createPayMongoBalanceCheckout" wire:loading.attr="disabled" wire:target="createPayMongoBalanceCheckout" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-70">';
            $html .= '<span wire:loading.remove wire:target="createPayMongoBalanceCheckout">Collect Remaining Balance Online</span>';
            $html .= '<span wire:loading wire:target="createPayMongoBalanceCheckout">Generating checkout...</span>';
            $html .= '</button>';
        } else {
            $html .= '<div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">';
            $html .= 'PayMongo balance collection is unavailable for the current form state. Staff may use the manual payment fields below.';
            $html .= '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected function getOnlinePaymentSummary(): array
    {
        $payments = $this->record->payments()
            ->where('gateway', 'paymongo')
            ->get();

        return [
            'deposit_paid' => (float) $payments
                ->where('is_deposit', true)
                ->where('gateway_status', 'paid')
                ->where('status', 'posted')
                ->sum('amount'),
            'balance_paid' => (float) $payments
                ->where('is_deposit', false)
                ->where('gateway_status', 'paid')
                ->where('status', 'posted')
                ->sum('amount'),
            'balance_pending' => (float) $payments
                ->where('is_deposit', false)
                ->where('gateway_status', 'pending')
                ->where('status', 'pending')
                ->sum('amount'),
            'remaining_balance' => $this->getRemainingBalance(),
        ];
    }

    protected function getRemainingBalance(): float
    {
        $pricing = ReservationResource::computeCheckInPricing(
            $this->record,
            $this->data['reservation_rooms'] ?? [],
            $this->data['detailed_checkin_datetime'] ?? null,
            $this->data['detailed_checkout_datetime'] ?? null,
            $this->data['additional_requests'] ?? [],
            (bool) ($this->data['is_pwd'] ?? false),
            (bool) ($this->data['is_senior_citizen'] ?? false),
            (bool) ($this->data['is_student'] ?? false)
        );

        $existingPayments = (float) $this->record->payments()
            ->where('status', 'posted')
            ->sum('amount');

        return round(max(0, (float) $pricing['grand_total'] - $existingPayments), 2);
    }

    protected function getPendingCheckInBalancePayment(): ?ReservationPayment
    {
        return $this->record->payments()
            ->where('gateway', 'paymongo')
            ->where('is_deposit', false)
            ->where('gateway_status', 'pending')
            ->where('status', 'pending')
            ->where('meta->source', 'checkin_balance')
            ->latest('id')
            ->first();
    }

    protected function getPaidCheckInBalancePayment(): ?ReservationPayment
    {
        return $this->record->payments()
            ->where('gateway', 'paymongo')
            ->where('is_deposit', false)
            ->where('gateway_status', 'paid')
            ->where('status', 'posted')
            ->where('meta->source', 'checkin_balance')
            ->latest('id')
            ->first()
            ?? $this->record->payments()
                ->where('gateway', 'paymongo')
                ->where('is_deposit', false)
                ->where('gateway_status', 'paid')
                ->where('status', 'posted')
                ->latest('id')
                ->first();
    }

    protected function getOfficialReceiptNumber(): ?string
    {
        $orNumber = $this->record->roomAssignments()
            ->whereNotNull('payment_or_number')
            ->latest('id')
            ->value('payment_or_number');

        if (blank($orNumber) || strtoupper(trim((string) $orNumber)) === 'N/A') {
            return null;
        }

        return trim((string) $orNumber);
    }

    protected function canCreatePayMongoBalanceCheckout(): bool
    {
        return Setting::isOnlinePaymentsEnabled()
            && in_array($this->record->status, ['approved', 'confirmed'], true)
            && $this->getRemainingBalance() > 0.01
            && ! $this->getPendingCheckInBalancePayment();
    }

    protected function shouldDisableManualPaymentFields(): bool
    {
        return $this->getRemainingBalance() <= 0.01 || (bool) $this->getPendingCheckInBalancePayment();
    }

    protected function absoluteUrl(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://'])
            ? $url
            : rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
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

    protected function getGenderOptions(): array
    {
        return [
            'Male' => 'Male',
            'Female' => 'Female',
            'Other' => 'Other',
        ];
    }
}
