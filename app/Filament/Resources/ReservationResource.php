<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\ForceDeletionLog;
use App\Models\Guest;
use App\Models\GuestAccount;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\RoomType;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Service;
use App\Models\Setting;
use App\Services\CheckInService;
use App\Services\InStayAddonService;
use App\Services\InStayExtensionService;
use App\Services\AlternativeRoomOfferService;
use App\Services\RoomHoldService;
use App\Services\ReservationWorkflowService;
use App\Services\PreStayReservationAmendmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Guest Information')
                    ->schema([
                        Forms\Components\Select::make('guest_account_id')
                            ->label('Linked Guest Account')
                            ->relationship('guestAccount', 'email')
                            ->getOptionLabelFromRecordUsing(fn (GuestAccount $record) => $record->name.' <'.$record->email.'>')
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $account = GuestAccount::find($state);

                                if (! $account) {
                                    return;
                                }

                                $set('guest_last_name', $account->last_name);
                                $set('guest_first_name', $account->first_name);
                                $set('guest_middle_initial', $account->middle_initial);
                                $set('guest_age', $account->age);
                                $set('guest_email', $account->email);
                                $set('guest_phone', $account->phone);
                                $set('guest_gender', $account->gender);
                                $set('guest_address', $account->address);
                            })
                            ->helperText('Optional account link. Reservation guest fields remain the submitted snapshot.')
                            ->visible(fn () => auth()->user()?->hasPermission('guest_accounts_edit') ?? false)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('guest_last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_middle_initial')
                            ->label('Middle Initial')
                            ->maxLength(10)
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_age')
                            ->label('Age')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(18)
                            ->maxValue(120)
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_phone')
                            ->label('Mobile Number')
                            ->tel()
                            ->required()
                            ->regex('/^(09\d{9}|\+639\d{9}|639\d{9})$/')
                            ->maxLength(20)
                            ->helperText('Use 09171234567, +639171234567, or 639171234567.')
                            ->validationMessages([
                                'regex' => 'Enter a valid Philippine mobile number, e.g. 09171234567 or +639171234567.',
                            ])
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\Select::make('guest_gender')
                            ->label('Gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                                'Other' => 'Other',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\Textarea::make('guest_address')
                            ->rows(2)
                            ->maxLength(1000)
                            ->live(onBlur: true)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
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
                            ->searchable()
                            ->live()
                            ->hiddenOn('create')
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\DatePicker::make('check_in_date')
                            ->required()
                            ->minDate(today())
                            ->native(false)
                            ->live()
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\DatePicker::make('check_out_date')
                            ->required()
                            ->after('check_in_date')
                            ->minDate(fn (Get $get): Carbon => filled($get('check_in_date'))
                                ? Carbon::parse($get('check_in_date'))->addDay()
                                : today()->addDay())
                            ->native(false)
                            ->live()
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('number_of_occupants')
                            ->label('Number of Occupants')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (Get $get): int => static::maximumOccupantsForRoomType($get('preferred_room_type_id')))
                            ->default(1)
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get): string => 'Maximum '.static::maximumOccupantsForRoomType($get('preferred_room_type_id')).' guest(s) for one selected room.')
                            ->hiddenOn('create'),
                        Forms\Components\Select::make('purpose')
                            ->options([
                                'academic' => 'Academic',
                                'official' => 'Official Business',
                                'personal' => 'Personal',
                                'event' => 'Event / Conference',
                                'other' => 'Other',
                            ])
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\Textarea::make('special_requests')
                            ->rows(3)
                            ->maxLength(2000)
                            ->live(onBlur: true)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\Repeater::make('direct_room_assignments')
                            ->label('Room Assignment')
                            ->visibleOn('create')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(7)
                            ->addActionLabel('Add another room type or capacity')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(function (array $state): ?string {
                                $roomType = RoomType::find($state['room_type_id'] ?? null);
                                $roomCount = count((array) ($state['room_ids'] ?? []));

                                if (! $roomType) {
                                    return 'Select rooms';
                                }

                                $capacity = filled($state['requested_capacity'] ?? null)
                                    ? ' — up to '.$state['requested_capacity'].' guests'
                                    : '';

                                return $roomType->name.$capacity.' ('.$roomCount.' room'.($roomCount === 1 ? '' : 's').')';
                            })
                            ->schema([
                                Forms\Components\Select::make('room_type_id')
                                    ->label('Room Type')
                                    ->options(fn (): array => RoomType::query()
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $roomType = RoomType::find($state);
                                        $capacities = $roomType
                                            ? app(RoomHoldService::class)->getSellableCapacities($roomType)
                                            : [];

                                        $set('requested_capacity', count($capacities) === 1 ? $capacities[0] : null);
                                        $set('room_ids', []);
                                    }),
                                Forms\Components\Select::make('requested_capacity')
                                    ->label('Room Capacity')
                                    ->options(fn (Get $get): array => static::directAssignmentCapacityOptions($get('room_type_id')))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (callable $set) => $set('room_ids', []))
                                    ->helperText('Select the capacity of the rooms being reserved.'),
                                Forms\Components\TextInput::make('occupant_count')
                                    ->label('Guests in these rooms')
                                    ->required()
                                    ->integer()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(fn (Get $get): int => static::directAssignmentMaximumOccupants(
                                        $get('room_type_id'),
                                        $get('requested_capacity'),
                                        $get('room_ids')
                                    ))
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->helperText(fn (Get $get): string => static::directAssignmentOccupantHelp(
                                        $get('room_type_id'),
                                        $get('requested_capacity'),
                                        $get('room_ids')
                                    )),
                                Forms\Components\Select::make('room_ids')
                                    ->label('Available Rooms')
                                    ->multiple()
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->options(fn (Get $get): array => static::directAssignmentRoomOptions(
                                        $get('room_type_id'),
                                        $get('requested_capacity'),
                                        $get('../../check_in_date'),
                                        $get('../../check_out_date'),
                                    ))
                                    ->helperText('Only rooms available for the selected dates and capacity are shown.')
                                    ->live(),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Line Notes')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Staff Notes')
                    ->schema([
                        Forms\Components\Placeholder::make('direct_booking_status')
                            ->label('Direct booking status')
                            ->visibleOn('create')
                            ->content('Staff-created reservations begin pending. Selected room requests are revalidated and held only when a staff member approves the reservation.'),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Staff Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Check-In Details')
                    ->description('Deprecated: in-stay details are changed only through audited workflow actions.')
                    ->visible(false)
                    ->schema([
                        Forms\Components\Select::make('checkin_id_type')
                            ->label('ID Type')
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
                        Forms\Components\TextInput::make('checkin_id_number')
                            ->label('ID Number')
                            ->maxLength(100),
                        Forms\Components\Select::make('checkin_nationality')
                            ->label('Nationality')
                            ->default('Filipino')
                            ->searchable()
                            ->options([
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
                            ]),
                        Forms\Components\Select::make('checkin_purpose_of_stay')
                            ->label('Purpose of Stay')
                            ->options([
                                'Academic' => 'Academic',
                                'Official Business' => 'Official Business',
                                'Personal' => 'Personal',
                                'Event/Conference' => 'Event/Conference',
                                'Training' => 'Training',
                                'Research' => 'Research',
                                'Other' => 'Other',
                            ]),
                        Forms\Components\Toggle::make('checkin_is_student')
                            ->label('Student')
                            ->inline(false)
                            ->live()
                            ->afterStateUpdated(fn ($set, $get, $record) => static::recalculateDiscountedTotal($set, $get, $record)),
                        Forms\Components\Toggle::make('checkin_is_senior_citizen')
                            ->label('Senior Citizen')
                            ->inline(false)
                            ->live()
                            ->afterStateUpdated(fn ($set, $get, $record) => static::recalculateDiscountedTotal($set, $get, $record)),
                        Forms\Components\Toggle::make('checkin_is_pwd')
                            ->label('PWD')
                            ->inline(false)
                            ->live()
                            ->afterStateUpdated(fn ($set, $get, $record) => static::recalculateDiscountedTotal($set, $get, $record)),
                        Forms\Components\DatePicker::make('checkin_detailed_checkin_datetime')
                            ->label('Date of Arrival')
                            ->native(false),
                        Forms\Components\DatePicker::make('checkin_detailed_checkout_datetime')
                            ->label('Scheduled Check-out Date')
                            ->native(false),
                        Forms\Components\Repeater::make('checkin_additional_requests')
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
                            ->live()
                            ->afterStateUpdated(fn ($set, $get, $record) => static::recalculateDiscountedTotal($set, $get, $record))
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('checkin_pricing_breakdown')
                            ->label('Pricing Breakdown')
                            ->content(function ($get, $record) {
                                if (! $record) {
                                    return 'No pricing data available.';
                                }

                                // Calculate nights from reservation dates
                                $nights = max(1, $record->check_in_date->diffInDays($record->check_out_date));

                                // Calculate room charges
                                $assignments = $record->roomAssignments()->with('room.roomType')->get()->unique('room_id');
                                $roomCharges = 0;
                                $roomLines = [];

                                foreach ($assignments as $assignment) {
                                    if ($assignment->room && $assignment->room->roomType) {
                                        $rate = (float) $assignment->room->roomType->base_rate;
                                        $lineTotal = $rate * $nights;
                                        $roomCharges += $lineTotal;
                                        $roomLines[] = "Room {$assignment->room->room_number}: PHP ".number_format($rate, 2)." x {$nights} night(s) = PHP ".number_format($lineTotal, 2);
                                    }
                                }

                                // Calculate add-ons
                                $addonItems = collect($get('checkin_additional_requests') ?? [])
                                    ->filter(fn ($i) => ! empty($i['code'] ?? null));
                                $addonsTotal = 0;
                                $addonLines = [];

                                if ($addonItems->isNotEmpty()) {
                                    $addons = Service::query()->whereIn('code', $addonItems->pluck('code')->unique())->get()->keyBy('code');
                                    foreach ($addonItems as $item) {
                                        $qty = max(1, (int) ($item['qty'] ?? 1));
                                        $addon = $addons->get($item['code']);
                                        if ($addon) {
                                            $lineTotal = (float) $addon->price * $qty;
                                            $addonsTotal += $lineTotal;
                                            $addonLines[] = "{$qty}x {$addon->name}: PHP ".number_format($lineTotal, 2);
                                        }
                                    }
                                }

                                $subtotal = $roomCharges + $addonsTotal;

                                // Calculate discount
                                $isPwd = (bool) ($get('checkin_is_pwd') ?? false);
                                $isSenior = (bool) ($get('checkin_is_senior_citizen') ?? false);
                                $isStudent = (bool) ($get('checkin_is_student') ?? false);

                                $pwdPercent = (float) Setting::get('discount_pwd_percent', 0);
                                $seniorPercent = (float) Setting::get('discount_senior_percent', 0);
                                $studentPercent = (float) Setting::get('discount_student_percent', 0);

                                // Pick only the highest applicable discount
                                $candidates = [];

                                if ($isPwd && $pwdPercent > 0) {
                                    $candidates[] = ['label' => "PWD: {$pwdPercent}%", 'percent' => $pwdPercent];
                                }

                                if ($isSenior && $seniorPercent > 0) {
                                    $candidates[] = ['label' => "Senior Citizen: {$seniorPercent}%", 'percent' => $seniorPercent];
                                }

                                if ($isStudent && $studentPercent > 0) {
                                    $candidates[] = ['label' => "Student: {$studentPercent}%", 'percent' => $studentPercent];
                                }

                                $discountLines = [];
                                $totalDiscountPercent = 0;

                                if (! empty($candidates)) {
                                    usort($candidates, fn ($a, $b) => $b['percent'] <=> $a['percent']);
                                    $best = $candidates[0];
                                    $discountLines[] = $best['label'];
                                    $totalDiscountPercent = $best['percent'];
                                }

                                $totalDiscountPercent = min($totalDiscountPercent, 100);
                                $discountAmount = ($subtotal * $totalDiscountPercent) / 100;
                                $grandTotal = max(0, $subtotal - $discountAmount);

                                $html = '<div class="text-sm space-y-2">';
                                $html .= '<div><strong>Nights:</strong> '.$nights.'</div>';

                                if (! empty($roomLines)) {
                                    $html .= '<div class="mt-2"><strong>Room Charges:</strong></div>';
                                    $html .= '<ul class="list-disc pl-5">';
                                    foreach ($roomLines as $line) {
                                        $html .= '<li>'.e($line).'</li>';
                                    }
                                    $html .= '</ul>';
                                }

                                if (! empty($addonLines)) {
                                    $html .= '<div class="mt-2"><strong>Add-Ons:</strong></div>';
                                    $html .= '<ul class="list-disc pl-5">';
                                    foreach ($addonLines as $line) {
                                        $html .= '<li>'.e($line).'</li>';
                                    }
                                    $html .= '</ul>';
                                } else {
                                    $html .= '<div class="mt-2"><strong>Add-Ons:</strong> None</div>';
                                }

                                $html .= '<div class="mt-2"><strong>Room Subtotal:</strong> PHP '.number_format($roomCharges, 2).'</div>';
                                $html .= '<div><strong>Add-Ons Total:</strong> PHP '.number_format($addonsTotal, 2).'</div>';
                                $html .= '<div><strong>Subtotal:</strong> PHP '.number_format($subtotal, 2).'</div>';

                                if (! empty($discountLines)) {
                                    $html .= '<div class="mt-2 text-green-600"><strong>Discounts Applied:</strong></div>';
                                    $html .= '<ul class="list-disc pl-5 text-green-600">';
                                    foreach ($discountLines as $line) {
                                        $html .= '<li>'.e($line).'</li>';
                                    }
                                    $html .= '</ul>';
                                    $html .= '<div class="text-green-600"><strong>Total Discount ('.$totalDiscountPercent.'%):</strong> -PHP '.number_format($discountAmount, 2).'</div>';
                                }

                                $html .= '<div class="font-semibold text-lg mt-2"><strong>Grand Total:</strong> PHP '.number_format($grandTotal, 2).'</div>';
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                        Forms\Components\Select::make('checkin_payment_mode')
                            ->label('Payment Mode')
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                                'gcash' => 'GCash',
                                'check' => 'Check',
                                'others' => 'Others',
                            ])
                            ->live(),
                        Forms\Components\TextInput::make('checkin_payment_mode_other')
                            ->label('Specify Payment Mode')
                            ->maxLength(100)
                            ->visible(fn ($get) => $get('checkin_payment_mode') === 'others'),
                        Forms\Components\TextInput::make('checkin_payment_amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->prefix('PHP')
                            ->minValue(0)
                            ->required()
                            ->helperText('Updates automatically when add-ons change. Adjust manually if needed.'),
                        Forms\Components\TextInput::make('checkin_payment_or_number')
                            ->label('Official Receipt Number')
                            ->maxLength(100),
                        Forms\Components\DatePicker::make('checkin_or_date')
                            ->label('OR Date')
                            ->displayFormat('M d, Y')
                            ->default(now()->toDateString())
                            ->helperText('Date on the official receipt'),
                        Forms\Components\Textarea::make('checkin_remarks')
                            ->label('Check-in Remarks')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Reservation Overview')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Guest Information')
                            ->schema([
                                Infolists\Components\Section::make('Primary Guest Information')
                                    ->description('Main contact and profile details for this reservation.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('guestAccount.email')
                                            ->label('Linked Guest Account')
                                            ->placeholder('No linked account')
                                            ->url(fn (Reservation $record) => $record->guestAccount ? GuestAccountResource::getUrl('view', ['record' => $record->guestAccount]) : null)
                                            ->openUrlInNewTab(),
                                        Infolists\Components\TextEntry::make('guest_last_name')
                                            ->label('Last Name'),
                                        Infolists\Components\TextEntry::make('guest_first_name')
                                            ->label('First Name'),
                                        Infolists\Components\TextEntry::make('guest_middle_initial')
                                            ->label('Middle Initial')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('guest_email')
                                            ->label('Guest Email'),
                                        Infolists\Components\TextEntry::make('guest_phone')
                                            ->label('Guest Phone')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('guest_gender')
                                            ->label('Gender')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('guest_age')
                                            ->label('Age')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('guest_address')
                                            ->label('Guest Address')
                                            ->columnSpanFull()
                                            ->placeholder('-'),
                                    ])->columns(3),

                                Infolists\Components\Section::make('Billing & Add-On Overview')
                                    ->description('Payment and add-on details captured from the latest check-in/payment snapshot.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('billing_guest_name')
                                            ->label('Billing Guest')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['guest_name'] ?? $record->guest_name),
                                        Infolists\Components\TextEntry::make('billing_payment_mode')
                                            ->label('Payment Mode')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['payment_mode'] ?? '-')
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('billing_payment_amount')
                                            ->label('Total Payment Amount')
                                            ->money('PHP')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['payment_amount']),
                                        Infolists\Components\TextEntry::make('billing_outstanding_balance')
                                            ->label('Outstanding Balance')
                                            ->money('PHP')
                                            ->default(fn (Reservation $record) => (float) $record->balance_due)
                                            ->color(fn (Reservation $record) => (float) $record->balance_due > 0.01 ? 'danger' : 'success'),
                                        Infolists\Components\TextEntry::make('billing_or_number')
                                            ->label('Official Receipt Number')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['or_number'] ?? '-')
                                            ->copyable(),
                                        Infolists\Components\TextEntry::make('billing_online_reference')
                                            ->label('Online Payment Reference')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['online_reference'] ?? '-')
                                            ->copyable()
                                            ->visible(fn (Reservation $record) => filled(self::resolveBillingSnapshot($record)['online_reference'] ?? null)),
                                        Infolists\Components\TextEntry::make('billing_or_date')
                                            ->label('Payment / OR Date')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['or_date']
                                                ? \Carbon\Carbon::parse(self::resolveBillingSnapshot($record)['or_date'])->format('M d, Y')
                                                : '-'),
                                        Infolists\Components\TextEntry::make('billing_discount_availed')
                                            ->label('Discount Availed')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_label'])
                                            ->badge()
                                            ->color(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_applied'] ? 'success' : 'gray'),
                                        Infolists\Components\TextEntry::make('billing_discount_amount')
                                            ->label('Discount Amount')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_applied']
                                                ? '-PHP '.number_format((float) self::resolveBillingSnapshot($record)['discount_amount'], 2)
                                                : '-'),
                                        Infolists\Components\TextEntry::make('billing_addons')
                                            ->label('Add-Ons')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['addons_label'])
                                            ->columnSpanFull()
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('billing_in_stay_addons')
                                            ->label('In-Stay Add-On Ledger')
                                            ->default(function (Reservation $record): string {
                                                $charges = $record->charges()
                                                    ->whereIn('charge_type', ['addon', 'discount'])
                                                    ->get()
                                                    ->filter(fn (ReservationCharge $charge) => str_starts_with((string) data_get($charge->meta, 'source', ''), 'in_stay_addon'));

                                                if ($charges->isEmpty()) {
                                                    return 'No in-stay add-ons posted.';
                                                }

                                                return $charges->map(fn (ReservationCharge $charge) => $charge->created_at->format('M d, g:i A').' — '.$charge->description.' (PHP '.number_format((float) $charge->amount, 2).')')->implode("\n");
                                            })
                                            ->columnSpanFull()
                                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                                    ])->columns(2),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Check-In Information')
                            ->schema([
                                Infolists\Components\Section::make('Check-In Status')
                                    ->description('Current reservation progress and check-in processing state.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('status')
                                            ->label('Reservation Status')
                                            ->badge()
                                            ->formatStateUsing(fn ($state) => Reservation::statusPresentation((string) $state)['admin_label'])
                                            ->color(fn ($state) => Reservation::statusPresentation((string) $state)['filament_color']),
                                        Infolists\Components\TextEntry::make('feedback_status')
                                            ->label('Guest Feedback')
                                            ->default(fn (Reservation $record) => $record->feedback ? 'Submitted' : 'Not submitted')
                                            ->badge()
                                            ->color(fn (Reservation $record) => $record->feedback ? 'success' : 'gray')
                                            ->url(fn (Reservation $record) => $record->feedback ? ReservationFeedbackResource::getUrl('view', ['record' => $record->feedback]) : null)
                                            ->openUrlInNewTab(),
                                        Infolists\Components\TextEntry::make('checked_in_guests')
                                            ->label('Checked-In Guests')
                                            ->default(function (Reservation $record) {
                                                $checkedIn = $record->roomAssignments()->whereNotNull('checked_in_at')->count();

                                                return $checkedIn.' / '.((int) $record->number_of_occupants ?: $checkedIn);
                                            }),
                                        Infolists\Components\TextEntry::make('checked_in_by_name')
                                            ->label('Last Processed By')
                                            ->default(function (Reservation $record) {
                                                // Try last check-in
                                                $lastCheckedIn = $record->roomAssignments()->with('assignedByUser')->latest('checked_in_at')->first();
                                                if ($lastCheckedIn && $lastCheckedIn->assignedByUser) {
                                                    return $lastCheckedIn->assignedByUser->name;
                                                }
                                                // Try last assignment
                                                $lastAssigned = $record->roomAssignments()->with('assignedByUser')->latest('assigned_at')->first();
                                                if ($lastAssigned && $lastAssigned->assignedByUser) {
                                                    return $lastAssigned->assignedByUser->name;
                                                }
                                                return '-';
                                            }),
                                    ])->columns(4),

                                Infolists\Components\Section::make('Captured Check-In Snapshot')
                                    ->description('Identity and stay details saved during completed check-in.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('snapshot_discount_availed')
                                            ->label('Discount Availed')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_label'])
                                            ->badge()
                                            ->color(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_applied'] ? 'success' : 'gray'),
                                        Infolists\Components\TextEntry::make('snapshot_discount_amount')
                                            ->label('Discount Amount')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_applied']
                                                ? '-PHP '.number_format((float) self::resolveBillingSnapshot($record)['discount_amount'], 2)
                                                : '-'),
                                        Infolists\Components\TextEntry::make('snapshot_id_type')
                                            ->label('ID Type')
                                            ->default(fn (Reservation $record) => self::resolveCheckInSnapshot($record)['id_type'] ?? '-')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('snapshot_id_number')
                                            ->label('ID Number')
                                            ->default(fn (Reservation $record) => self::resolveCheckInSnapshot($record)['id_number'] ?? '-')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('snapshot_nationality')
                                            ->label('Nationality')
                                            ->default(fn (Reservation $record) => self::resolveCheckInSnapshot($record)['nationality'] ?? '-')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('snapshot_purpose')
                                            ->label('Purpose of Stay')
                                            ->default(fn (Reservation $record) => self::resolveCheckInSnapshot($record)['purpose'] ?? '-')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('snapshot_checkin_at')
                                            ->label('Date & Time of Arrival')
                                            ->default(fn (Reservation $record) => self::resolveCheckInSnapshot($record)['checkin_at'] ?? '-')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('snapshot_checkout_at')
                                            ->label('Scheduled Check-out')
                                            ->default(fn (Reservation $record) => self::resolveCheckInSnapshot($record)['checkout_at'] ?? '-')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('actual_checkin_at')
                                            ->label('Official Check-in (Payment)')
                                            ->default(function (Reservation $record) {
                                                $ts = $record->roomAssignments()->whereNotNull('checked_in_at')->oldest('checked_in_at')->value('checked_in_at');

                                                return $ts ? Carbon::parse($ts)->format('M d, Y') : '-';
                                            })
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('actual_checkout_at')
                                            ->label('Actual Check-out')
                                            ->default(function (Reservation $record) {
                                                $ts = $record->roomAssignments()->whereNotNull('checked_out_at')->latest('checked_out_at')->value('checked_out_at');

                                                return $ts ? Carbon::parse($ts)->format('M d, Y') : '-';
                                            })
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('snapshot_remarks')
                                            ->label('Check-In Remarks')
                                            ->default(fn (Reservation $record) => self::resolveCheckInSnapshot($record)['remarks'] ?? '-')
                                            ->columnSpanFull()
                                            ->placeholder('-'),
                                    ])->columns(2),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Reservation & Notes')
                            ->schema([
                                Infolists\Components\Section::make('Reservation Details')
                                    ->description('Reference and booking details entered during reservation request.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('reference_number')
                                            ->label('Reference Number')
                                            ->copyable(),
                                        Infolists\Components\TextEntry::make('preferredRoomType.name')
                                            ->label('Preferred Room Type')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('requested_room_summary')
                                            ->label('Requested Rooms')
                                            ->getStateUsing(fn (Reservation $record) => $record->requested_room_summary)
                                            ->columnSpanFull()
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('check_in_date')
                                            ->label('Check In Date')
                                            ->date(),
                                        Infolists\Components\TextEntry::make('check_out_date')
                                            ->label('Check Out Date')
                                            ->date(),
                                        Infolists\Components\TextEntry::make('number_of_occupants')
                                            ->label('Declared Number of Guests')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('purpose')
                                            ->label('Purpose')
                                            ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', (string) $state)) : '-'),
                                        Infolists\Components\TextEntry::make('special_requests')
                                            ->label('Special Requests')
                                            ->columnSpanFull()
                                            ->placeholder('-'),
                                    ])->columns(3),

                                Infolists\Components\Section::make('Review & Notes')
                                    ->description('Internal notes and review timestamps.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('admin_notes')
                                            ->label('Staff Notes')
                                            ->columnSpanFull()
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('reviewed_at')
                                            ->label('Reviewed At')
                                            ->date()
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Submitted At')
                                            ->date(),
                                    ])->columns(2),

                                Infolists\Components\Section::make('Online Payment Link')
                                    ->description('Secure payment link for room-held reservations via GCash, Maya, or Card.')
                                    ->visible(fn (Reservation $record) => Setting::isOnlinePaymentsEnabled() &&
                                        $record->payment_link_token &&
                                        $record->canAcceptGuestPayment()
                                    )
                                    ->schema([
                                        Infolists\Components\TextEntry::make('payment_link')
                                            ->label('Payment Link')
                                            ->default(fn (Reservation $record) => $record->generatePaymentLink())
                                            ->copyable()
                                            ->columnSpanFull()
                                            ->color(fn (Reservation $record) => $record->isPaymentLinkValid() ? 'success' : 'danger')
                                            ->icon(fn (Reservation $record) => $record->isPaymentLinkValid() ? 'heroicon-o-link' : 'heroicon-o-x-circle'),
                                        Infolists\Components\TextEntry::make('payment_link_expires_at')
                                            ->label('Link Expires')
                                            ->dateTime('M d, Y g:i A')
                                            ->color(fn (?string $state) => $state && \Carbon\Carbon::parse($state)->isPast() ? 'danger' : 'warning'),
                                        Infolists\Components\ImageEntry::make('payment_qr_code')
                                            ->label('QR Code')
                                            ->default(fn (Reservation $record) => route('guest.payment.qr', ['token' => $record->payment_link_token]))
                                            ->height(200)
                                            ->extraAttributes(['style' => 'border: 2px solid #e5e7eb; border-radius: 8px; padding: 8px;'])
                                            ->visible(fn (Reservation $record) => $record->isPaymentLinkValid()),
                                    ])->columns(2),

                                // Deposit status section shown whenever gateway payments exist, regardless of toggle
                                Infolists\Components\Section::make('Online Deposit Status')
                                    ->description('Status of online deposit payment for this reservation.')
                                    ->visible(fn (Reservation $record) => $record->payments()
                                        ->where('gateway', 'paymongo')
                                        ->where('is_deposit', true)
                                        ->exists()
                                    )
                                    ->schema([
                                        Infolists\Components\TextEntry::make('payment_status_info')
                                            ->label('Payment Status')
                                            ->default(function (Reservation $record) {
                                                $gatewayPayment = $record->payments()
                                                    ->where('gateway', 'paymongo')
                                                    ->where('is_deposit', true)
                                                    ->latest()
                                                    ->first();

                                                if (! $gatewayPayment) {
                                                    return 'No online payment received yet';
                                                }

                                                return match ($gatewayPayment->gateway_status) {
                                                    'paid' => 'Deposit Paid Online - PHP '.number_format($gatewayPayment->amount, 2),
                                                    'pending' => 'Payment Pending',
                                                    'failed' => 'Payment Failed',
                                                    default => $gatewayPayment->gateway_status,
                                                };
                                            })
                                            ->badge()
                                            ->color(function (Reservation $record) {
                                                $gatewayPayment = $record->payments()
                                                    ->where('gateway', 'paymongo')
                                                    ->where('is_deposit', true)
                                                    ->latest()
                                                    ->first();

                                                if (! $gatewayPayment) {
                                                    return 'gray';
                                                }

                                                return match ($gatewayPayment->gateway_status) {
                                                    'paid' => 'success',
                                                    'pending' => 'warning',
                                                    'failed' => 'danger',
                                                    default => 'gray',
                                                };
                                            }),
                                    ])->columns(2),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Held Rooms')
                            ->visible(fn (?Reservation $record) => $record?->roomHolds()->exists())
                            ->badge(fn (?Reservation $record) => $record?->roomHolds()->count() ?: null)
                            ->schema([
                                Infolists\Components\Section::make('Reserved Room Inventory')
                                    ->description('These rooms are held exclusively for this reservation and are blocked from others.')
                                    ->schema([
                                        Infolists\Components\RepeatableEntry::make('roomHolds')
                                            ->schema([
                                                Infolists\Components\TextEntry::make('room.room_number')
                                                    ->label('Room')
                                                    ->badge()
                                                    ->color('primary'),
                                                Infolists\Components\TextEntry::make('hold_from')
                                                    ->label('From')
                                                    ->date('M d, Y'),
                                                Infolists\Components\TextEntry::make('hold_to')
                                                    ->label('To')
                                                    ->date('M d, Y'),
                                            ])
                                            ->columns(3)
                                            ->contained(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->width('120px'),
                Tables\Columns\TextColumn::make('guest_name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->width('150px'),
                Tables\Columns\TextColumn::make('guest_email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                Tables\Columns\TextColumn::make('guestAccount.email')
                    ->label('Account')
                    ->badge()
                    ->placeholder('Unlinked')
                    ->color(fn ($state) => filled($state) ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('requested_room_types')
                    ->label('Room Types')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $typesQuery) use ($search): void {
                        $typesQuery
                            ->whereHas('roomRequests.roomType', fn (Builder $roomTypeQuery) => $roomTypeQuery->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('preferredRoomType', fn (Builder $roomTypeQuery) => $roomTypeQuery->where('name', 'like', "%{$search}%"));
                    }))
                    ->wrap()
                    ->width('130px')
                    ->getStateUsing(function (Reservation $record) {
                        return $record->getEffectiveRoomRequests()
                            ->map(fn ($request) => $request->roomType?->name)
                            ->filter()
                            ->unique()
                            ->implode(', ') ?: '—';
                    }),
                Tables\Columns\TextColumn::make('room_display')
                    ->label('Room')
                    ->badge()
                    ->width('120px')
                    ->getStateUsing(function (Reservation $record) {
                        $info = $record->room_display_info;
                        $rooms = $info['rooms'] ?? [];
                        return empty($rooms) ? null : (count($rooms) === 1 ? $rooms[0] : $rooms);
                    })
                    ->color(fn (Reservation $record) => $record->room_display_info['color'] ?? 'gray')
                    ->tooltip(fn (Reservation $record) => $record->room_display_info['tooltip'] ?? null)
                    ->searchable(query: function ($query, string $search) {
                        $query->where(function ($q) use ($search) {
                            // Search in room assignments
                            $q->whereHas('roomAssignments.room', fn ($subQ) => $subQ->where('room_number', 'like', "%{$search}%")
                            )
                            // Also search in room holds
                                ->orWhereHas('roomHolds.room', fn ($subQ) => $subQ->where('room_number', 'like', "%{$search}%")
                                );
                        });
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('requested_room_summary')
                    ->label('Requested')
                    ->getStateUsing(fn (Reservation $record) => $record->requested_room_summary)
                    ->wrap()
                    ->toggleable()
                    ->width('190px'),
                Tables\Columns\TextColumn::make('check_in_date')
                    ->date()
                    ->searchable()
                    ->sortable()
                    ->width('110px'),
                Tables\Columns\TextColumn::make('check_out_date')
                    ->label('Check out date')
                    ->getStateUsing(function (Reservation $record) {
                        // If guest has actually checked out, show the real checkout time
                        $actualOut = $record->roomAssignments
                            ->whereNotNull('checked_out_at')
                            ->sortByDesc('checked_out_at')
                            ->first()?->checked_out_at;

                        if ($actualOut) {
                            return Carbon::parse($actualOut)->format('M d, Y');
                        }

                        // Still checked in: show scheduled checkout as a deadline
                        $scheduled = $record->roomAssignments
                            ->whereNotNull('detailed_checkout_datetime')
                            ->sortByDesc('detailed_checkout_datetime')
                            ->first()?->detailed_checkout_datetime;

                        if ($scheduled) {
                            return 'Due: '.Carbon::parse($scheduled)->format('M d, Y');
                        }

                        // Fallback to reservation-level date
                        return 'Due: '.Carbon::parse($record->check_out_date)->format('M d, Y');
                    })
                    ->searchable(false)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('check_out_date', $direction))
                    ->width('150px'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->width('120px')
                    ->formatStateUsing(fn ($state) => Reservation::statusPresentation((string) $state)['admin_label'])
                    ->color(fn ($state): array => Color::hex(Reservation::statusPresentation((string) $state)['hex'])),
                Tables\Columns\TextColumn::make('feedback.id')
                    ->label('Feedback')
                    ->badge()
                    ->getStateUsing(fn (Reservation $record) => $record->feedback ? 'Submitted' : 'None')
                    ->color(fn (string $state) => $state === 'Submitted' ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->url(fn (Reservation $record) => $record->feedback ? ReservationFeedbackResource::getUrl('view', ['record' => $record->feedback]) : null),
                Tables\Columns\TextColumn::make('payment_gateway_status')
                    ->label('Payment')
                    ->badge()
                    ->getStateUsing(fn (Reservation $record) => $record->payment_gateway_status)
                    ->color(fn (Reservation $record) => $record->payment_gateway_color)
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->width('120px'),
                Tables\Columns\TextColumn::make('discount_availed')
                    ->label('Discount')
                    ->badge()
                    ->getStateUsing(fn (Reservation $record) => $record->discount_info['label'])
                    ->color(fn (Reservation $record) => $record->discount_info['applied'] ? 'success' : 'gray')
                    ->sortable(false)
                    ->width('100px'),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->searchable()
                    ->sortable()
                    ->label('Submitted')
                    ->width('150px')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with([
                'roomAssignments.room.roomType',
                'preferredRoomType',
                'charges',
                'payments',
                'billingGuest',
                'guestAccount',
                'feedback',
                'roomHolds.room', // For room display info
                'roomRequests.roomType',
            ]))
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Reservation::statusOptions()),
                Tables\Filters\SelectFilter::make('preferred_room_type_id')
                    ->relationship('preferredRoomType', 'name')
                    ->label('Room Type')
                    ->preload(),
                Tables\Filters\TernaryFilter::make('guest_account_id')
                    ->label('Guest Account')
                    ->placeholder('All reservations')
                    ->trueLabel('Linked to account')
                    ->falseLabel('Unlinked')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('guest_account_id'),
                        false: fn (Builder $query) => $query->whereNull('guest_account_id'),
                        blank: fn (Builder $query) => $query,
                    ),
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
                Tables\Filters\Filter::make('near_due')
                    ->label('Near Due')
                    ->form([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Checkout within 24 hours'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['enabled'] ?? false) {
                            return $query
                                ->where('status', 'checked_in')
                                ->whereBetween('check_out_date', [
                                    now()->toDateString(),
                                    now()->addDay()->toDateString(),
                                ]);
                        }

                        return $query;
                    })
                    ->indicateUsing(fn (array $data): ?string => ($data['enabled'] ?? false) ? 'Near Due (checkout <= 24h)' : null),
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Check-outs')
                    ->form([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Past checkout date (still checked in)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['enabled'] ?? false) {
                            return $query
                                ->where('status', 'checked_in')
                                ->whereDate('check_out_date', '<', now()->toDateString());
                        }

                        return $query;
                    })
                    ->indicateUsing(fn (array $data): ?string => ($data['enabled'] ?? false) ? 'Overdue check-outs' : null),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->slideOver(),
                    Tables\Actions\EditAction::make()
                        ->slideOver()
                        ->visible(fn (Reservation $record) => $record->status === 'pending'),

                    // Approve action
                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->modalHeading('Approve Reservation')
                        ->successNotificationTitle('Reservation approved')
                        ->modalDescription('Approve this reservation only after every requested room has been selected and can be held. Rooms requested during reservation creation are preselected when they are still available.')
                        ->modalWidth('4xl')
                        ->visible(fn (Reservation $record) => $record->status === 'pending')
                        ->form(fn (Reservation $record) => [
                            Forms\Components\Section::make('Approval Details')
                                ->schema([
                                    Forms\Components\Textarea::make('admin_notes')
                                        ->label('Notes (optional)')
                                        ->rows(2),
                                ]),

                            Forms\Components\Section::make('Room Assignment')
                                ->description('Select every requested room to secure this reservation. If any requested type is unavailable, cancel and use Propose Alternative instead.')
                                ->schema(self::makeApprovalRoomAssignmentSchema($record))
                                ->collapsible(),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $result = app(ReservationWorkflowService::class)->approve($record, $data);

                            if ($result['hold_error']) {
                                Notification::make()
                                    ->title('Reservation was not approved because room holds failed: '.$result['hold_error'])
                                    ->warning()
                                    ->send();
                            } elseif ($result['room_count'] > 0) {
                                Notification::make()
                                    ->title('Reservation approved with '.$result['room_count'].' room(s) held.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Reservation approved')
                                    ->success()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('propose_alternative')
                        ->label('Propose Alternative')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('warning')
                        ->modalHeading('Propose Alternative Room')
                        ->modalDescription('The guest must explicitly accept this offer. Selected rooms are held for 24 hours only.')
                        ->visible(fn (Reservation $record) => $record->status === 'pending')
                        ->form(fn (Reservation $record) => static::makeAlternativeOfferSchema($record))
                        ->action(function (Reservation $record, array $data): void {
                            try {
                                $offer = app(AlternativeRoomOfferService::class)->propose($record, $data);
                                app(AlternativeRoomOfferService::class)->sendOfferEmail($offer);

                                Notification::make()
                                    ->success()
                                    ->title('Alternative offer sent and rooms held for 24 hours.')
                                    ->send();
                            } catch (\RuntimeException $exception) {
                                Notification::make()->danger()->title($exception->getMessage())->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()->warning()->title('Offer created, but the email could not be sent. Reopen the reservation and try again.')->send();
                            }
                        }),

                    Tables\Actions\Action::make('withdraw_alternative')
                        ->label('Withdraw Alternative Offer')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Withdraw Alternative Offer')
                        ->modalDescription('The temporary room holds will be released and the reservation will return to pending review.')
                        ->visible(fn (Reservation $record) => $record->status === 'awaiting_alternative_confirmation')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for withdrawal')
                                ->required()
                                ->rows(3)
                                ->maxLength(500),
                        ])
                        ->action(function (Reservation $record, array $data): void {
                            app(AlternativeRoomOfferService::class)->withdraw($record, $data['reason']);
                            Notification::make()->success()->title('Alternative offer withdrawn and temporary holds released.')->send();
                        }),

                    Tables\Actions\Action::make('modify_pre_stay')
                        ->label('Modify Pre-Stay Reservation')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->modalWidth('3xl')
                        ->modalDescription('Use this action to replace an occupied or unavailable held room before check-in. The selected rooms will be revalidated and held again.')
                        ->visible(fn (Reservation $record) => in_array($record->status, ['approved', 'confirmed'], true))
                        ->form(fn (Reservation $record) => [
                            Forms\Components\TextInput::make('guest_name')->default($record->guest_name)->required(),
                            Forms\Components\TextInput::make('guest_email')->email()->default($record->guest_email)->required(),
                            Forms\Components\TextInput::make('guest_phone')->default($record->guest_phone),
                            Forms\Components\DatePicker::make('check_in_date')->default($record->check_in_date)->required()->native(false),
                            Forms\Components\DatePicker::make('check_out_date')->default($record->check_out_date)->required()->native(false),
                            Forms\Components\TextInput::make('number_of_occupants')->numeric()->minValue(1)->default($record->number_of_occupants)->required(),
                            Forms\Components\Select::make('room_ids')->label('Held / Replacement Rooms')->multiple()->required()->searchable()
                                ->options(Room::query()->where('is_active', true)->whereNotIn('status', ['maintenance', 'inactive'])->orderBy('room_number')->pluck('room_number', 'id'))
                                ->default($record->roomHolds()->where('hold_type', 'advance')->pluck('room_id')->all()),
                            Forms\Components\Textarea::make('purpose')->default($record->purpose),
                            Forms\Components\Textarea::make('special_requests')->default($record->special_requests),
                        ])
                        ->action(function (Reservation $record, array $data): void {
                            app(PreStayReservationAmendmentService::class)->amend($record, $data);
                            Notification::make()->success()->title('Pre-stay reservation amended and holds revalidated.')->send();
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
                            app(ReservationWorkflowService::class)->decline($record, $data['admin_notes']);
                        }),

                    // Direct reception check-in with onsite payment.
                    Tables\Actions\Action::make('check_in')
                        ->label('Check In')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('success')
                        ->visible(fn (Reservation $record) => in_array($record->status, ['approved', 'confirmed']))
                        ->url(fn (Reservation $record): string => static::getUrl('check-in', ['record' => $record])),

                    Tables\Actions\Action::make('post_addon')
                        ->label('Post Add-On')
                        ->icon('heroicon-o-plus-circle')
                        ->color('primary')
                        ->modalHeading('Post In-Stay Add-On')
                        ->modalDescription('Posted add-ons increase the outstanding balance and are collected at checkout.')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in' && (auth()->user()?->can('update', $record) ?? false))
                        ->form([
                            Forms\Components\Repeater::make('items')
                                ->label('Add-Ons')
                                ->schema([
                                    Forms\Components\Select::make('code')
                                        ->label('Add-On')
                                        ->options(fn () => Service::active()->ordered()->get()->mapWithKeys(fn (Service $service) => [
                                            $service->code => $service->name.' ('.$service->formatted_price.')',
                                        ]))
                                        ->required()
                                        ->searchable()
                                        ->distinct(),
                                    Forms\Components\TextInput::make('qty')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->required(),
                                ])
                                ->minItems(1)
                                ->defaultItems(1)
                                ->addActionLabel('Add another add-on')
                                ->columns(2),
                            Forms\Components\Textarea::make('note')
                                ->label('Staff Note')
                                ->rows(2)
                                ->maxLength(500),
                        ])
                        ->action(function (Reservation $record, array $data): void {
                            try {
                                $charges = app(InStayAddonService::class)->post($record, $data['items'] ?? [], $data['note'] ?? null);
                                $record->refresh();

                                Notification::make()
                                    ->success()
                                    ->title('Add-on posted')
                                    ->body(count($charges).' add-on(s) posted. Outstanding balance: PHP '.number_format((float) $record->balance_due, 2).'.')
                                    ->send();
                            } catch (\RuntimeException $exception) {
                                Notification::make()->danger()->title($exception->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('void_addon')
                        ->label('Void Add-On')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->modalHeading('Void Posted Add-On')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in' && (auth()->user()?->can('update', $record) ?? false))
                        ->form(fn (Reservation $record) => [
                            Forms\Components\Select::make('charge_id')
                                ->label('Posted Add-On')
                                ->options(function () use ($record): array {
                                    $voidedIds = $record->charges()->where('charge_type', 'addon')->get()
                                        ->pluck('meta.voids_charge_id')->filter()->map(fn ($id) => (int) $id)->all();

                                    return $record->charges()
                                        ->where('charge_type', 'addon')
                                        ->get()
                                        ->filter(fn (ReservationCharge $charge) => data_get($charge->meta, 'source') === 'in_stay_addon' && ! in_array($charge->id, $voidedIds, true))
                                        ->mapWithKeys(fn (ReservationCharge $charge) => [$charge->id => $charge->description.' — PHP '.number_format((float) $charge->amount, 2).' ('.optional($charge->created_at)->format('M d, g:i A').')'])
                                        ->all();
                                })
                                ->required()
                                ->searchable(),
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for voiding')
                                ->required()
                                ->rows(3)
                                ->maxLength(500),
                        ])
                        ->action(function (Reservation $record, array $data): void {
                            try {
                                $charge = $record->charges()->findOrFail($data['charge_id']);
                                app(InStayAddonService::class)->void($record, $charge, $data['reason']);
                                $record->refresh();

                                Notification::make()
                                    ->success()
                                    ->title('Add-on voided')
                                    ->body('The reversal was posted. Outstanding balance: PHP '.number_format((float) $record->balance_due, 2).'.')
                                    ->send();
                            } catch (\RuntimeException $exception) {
                                Notification::make()->danger()->title($exception->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('extend_stay')
                        ->label('Extend Stay')
                        ->icon('heroicon-o-calendar-days')
                        ->color('primary')
                        ->modalHeading('Extend Checked-In Stay')
                        ->modalDescription('The current assigned room(s) must be available for every added night. The added room charge is posted to the guest balance.')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in' && (auth()->user()?->can('update', $record) ?? false))
                        ->form(fn (Reservation $record) => [
                            Forms\Components\DatePicker::make('new_checkout_date')->label('New Check-out Date')->required()->native(false)
                                ->minDate(Carbon::parse($record->check_out_date)->addDay()),
                            Forms\Components\Textarea::make('note')->label('Staff Note')->rows(2)->maxLength(500),
                        ])
                        ->action(function (Reservation $record, array $data): void {
                            try {
                                $charges = app(InStayExtensionService::class)->extend($record, $data['new_checkout_date'], $data['note'] ?? null);
                                $record->refresh();
                                Notification::make()->success()->title('Stay extended')->body(count($charges).' room extension charge(s) posted. Outstanding balance: PHP '.number_format((float) $record->balance_due, 2).'.')->send();
                            } catch (\RuntimeException $exception) {
                                Notification::make()->danger()->title($exception->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('void_stay_extension')
                        ->label('Void Stay Extension')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->modalHeading('Void Stay Extension')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in' && (auth()->user()?->can('update', $record) ?? false))
                        ->form(fn (Reservation $record) => [
                            Forms\Components\Select::make('charge_id')->label('Posted extension')->required()->searchable()
                                ->options($record->charges()->where('charge_type', 'room_rate')->get()->filter(fn (ReservationCharge $charge) => data_get($charge->meta, 'source') === 'in_stay_extension')->mapWithKeys(fn (ReservationCharge $charge) => [$charge->id => $charge->description.' — PHP '.number_format((float) $charge->amount, 2)])),
                            Forms\Components\Textarea::make('reason')->label('Reason for voiding')->required()->rows(3)->maxLength(500),
                        ])
                        ->action(function (Reservation $record, array $data): void {
                            try {
                                app(InStayExtensionService::class)->void($record, $record->charges()->findOrFail($data['charge_id']), $data['reason']);
                                $record->refresh();
                                Notification::make()->success()->title('Stay extension voided')->body('The reversing entries were posted.')->send();
                            } catch (\RuntimeException $exception) {
                                Notification::make()->danger()->title($exception->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('correct_guest_identity')
                        ->label('Correct Guest Identity')
                        ->icon('heroicon-o-identification')
                        ->color('gray')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in' && (auth()->user()?->can('update', $record) ?? false))
                        ->form(fn (Reservation $record) => [
                            Forms\Components\Select::make('assignment_id')->label('Checked-in guest')->required()->searchable()
                                ->options($record->roomAssignments()->where('status', 'checked_in')->get()->mapWithKeys(fn (RoomAssignment $a) => [$a->id => trim($a->guest_first_name.' '.$a->guest_last_name).' — Room '.$a->room?->room_number])),
                            Forms\Components\TextInput::make('guest_first_name')->required(),
                            Forms\Components\TextInput::make('guest_last_name')->required(),
                            Forms\Components\TextInput::make('id_number')->label('ID number'),
                            Forms\Components\Textarea::make('reason')->required()->rows(2)->columnSpanFull(),
                        ])
                        ->action(function (Reservation $record, array $data): void {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data): void {
                                $assignment = $record->roomAssignments()->lockForUpdate()->findOrFail($data['assignment_id']);
                                $before = $assignment->only(['guest_first_name', 'guest_last_name', 'id_number']);
                                $assignment->update(collect($data)->only(['guest_first_name', 'guest_last_name', 'id_number'])->all());
                                ReservationLog::record($record, 'guest_identity_corrected', 'Checked-in guest identity corrected.', ['assignment_id' => $assignment->id, 'before' => $before, 'reason' => $data['reason']]);
                            });
                            Notification::make()->success()->title('Guest identity correction recorded.')->send();
                        }),

                    Tables\Actions\Action::make('correct_room_assignment')
                        ->label('Correct Room Assignment')
                        ->icon('heroicon-o-home-modern')
                        ->color('gray')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in' && (auth()->user()?->can('update', $record) ?? false))
                        ->form(fn (Reservation $record) => [
                            Forms\Components\Select::make('assignment_id')->label('Checked-in guest')->required()->searchable()
                                ->options($record->roomAssignments()->where('status', 'checked_in')->get()->mapWithKeys(fn (RoomAssignment $a) => [$a->id => trim($a->guest_first_name.' '.$a->guest_last_name).' — Room '.$a->room?->room_number])),
                            Forms\Components\Select::make('room_id')->label('Correct room')->required()->searchable()
                                ->options(Room::query()->where('is_active', true)->whereNotIn('status', ['maintenance', 'inactive'])->orderBy('room_number')->pluck('room_number', 'id')),
                            Forms\Components\Textarea::make('reason')->required()->rows(2)->columnSpanFull(),
                        ])
                        ->action(function (Reservation $record, array $data): void {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data): void {
                                $assignment = $record->roomAssignments()->lockForUpdate()->findOrFail($data['assignment_id']);
                                $room = Room::query()->with('roomType')->lockForUpdate()->findOrFail($data['room_id']);
                                if ($assignment->room_id !== $room->id && $room->isFull()) {
                                    throw new \RuntimeException("Room {$room->room_number} is at capacity.");
                                }
                                $before = ['room_id' => $assignment->room_id, 'room_number' => $assignment->room?->room_number];
                                $assignment->update(['room_id' => $room->id]);
                                ReservationLog::record($record, 'room_assignment_corrected', 'Checked-in room assignment corrected.', ['assignment_id' => $assignment->id, 'before' => $before, 'room_id' => $room->id, 'reason' => $data['reason']]);
                            });
                            Notification::make()->success()->title('Room assignment correction recorded.')->send();
                        }),

                    // Check Out action
                    Tables\Actions\Action::make('check_out')
                        ->icon('heroicon-o-arrow-left-on-rectangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Check Out Guest')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in')
                        ->form(fn (Reservation $record) => [
                            Forms\Components\Placeholder::make('checkout_balance')
                                ->label('Final balance')
                                ->helperText(function () use ($record): string {
                                    $extensions = $record->charges()->get()->filter(fn (ReservationCharge $charge) => str_starts_with((string) data_get($charge->meta, 'source', ''), 'in_stay_extension'));
                                    return $extensions->isEmpty() ? '' : 'Extensions: '.$extensions->map(fn (ReservationCharge $charge) => $charge->description.' (PHP '.number_format((float) $charge->amount, 2).')')->implode('; ');
                                })
                                ->content(fn () => 'PHP '.number_format((float) $record->fresh()->balance_due, 2).' — charges and payments are reconciled when you submit.'),
                            Forms\Components\DatePicker::make('checked_out_at')
                                ->label('Check-out Date')
                                ->default(fn () => now())
                                ->required()
                                ->native(false),
                            Forms\Components\Section::make('Final Payment')
                                ->description('Required when there is an outstanding balance.')
                                ->visible(fn () => (float) $record->fresh()->balance_due > 0.01)
                                ->schema([
                                    Forms\Components\TextInput::make('payment_amount')
                                        ->label('Payment Amount')
                                        ->numeric()
                                        ->prefix('PHP')
                                        ->default(fn () => round((float) $record->fresh()->balance_due, 2))
                                        ->required(),
                                    Forms\Components\Select::make('payment_mode')
                                        ->label('Payment Method')
                                        ->options([
                                            'cash' => 'Cash',
                                            'bank_transfer' => 'Bank Transfer',
                                            'gcash' => 'GCash',
                                            'check' => 'Check',
                                            'others' => 'Others',
                                        ])
                                        ->required(),
                                    Forms\Components\TextInput::make('reference_no')
                                        ->label('Official Receipt / Reference No.')
                                        ->required(),
                                    Forms\Components\DatePicker::make('or_date')
                                        ->label('OR / Payment Date')
                                        ->default(fn () => now())
                                        ->required()
                                        ->native(false),
                                    Forms\Components\Textarea::make('payment_remarks')
                                        ->label('Payment Notes')
                                        ->rows(2),
                                ])
                                ->columns(2),
                            Forms\Components\Textarea::make('remarks')
                                ->label('Check-out Remarks')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            try {
                                app(ReservationWorkflowService::class)->settleAndCheckOut($record, [
                                    'amount' => $data['payment_amount'] ?? 0,
                                    'payment_mode' => $data['payment_mode'] ?? '',
                                    'reference_no' => $data['reference_no'] ?? '',
                                    'or_date' => $data['or_date'] ?? null,
                                    'remarks' => $data['payment_remarks'] ?? null,
                                ], $data['checked_out_at'] ?? now(), $data['remarks'] ?? null);

                                Notification::make()
                                    ->success()
                                    ->title('Checked Out')
                                    ->body('Final balance settled and all guests checked out successfully.')
                                    ->send();
                            } catch (\RuntimeException $exception) {
                                Notification::make()->danger()->title($exception->getMessage())->send();
                            }
                        }),

                    // Cancel action
                    Tables\Actions\Action::make('cancel')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Reservation $record) => in_array($record->status, ['pending', 'approved', 'confirmed']))
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Cancellation reason')
                                ->required()
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            app(ReservationWorkflowService::class)->cancel($record, $data['admin_notes']);
                        }),

                    // Force Delete action (super_admin only)
                    Tables\Actions\Action::make('force_delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn () => auth()->user()->isSuperAdmin())
                        ->modalHeading('Force Delete Reservation')
                        ->modalDescription(fn (Reservation $record) => new HtmlString(
                            '<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:12px;margin-bottom:12px;">'
                            .'<strong style="color:#dc2626;">WARNING: This action is irreversible.</strong><br>'
                            .'This will permanently delete reservation <strong>'.$record->reference_number.'</strong> ('
                            .e($record->guest_name).') and all associated data:'
                            .'<ul style="margin:8px 0 0 16px;color:#991b1b;">'
                            .'<li>'.$record->guests()->count().' guest record(s)</li>'
                            .'<li>'.$record->roomAssignments()->count().' room assignment(s)</li>'
                            .'<li>'.$record->charges()->count().' charge(s)</li>'
                            .'<li>'.$record->payments()->count().' payment(s)</li>'
                            .'<li>'.$record->logs()->count().' log(s)</li>'
                            .'<li>'.$record->checkInSnapshots()->count().' check-in snapshot(s)</li>'
                            .'</ul></div>'
                        ))
                        ->modalSubmitActionLabel('Force Delete')
                        ->form([
                            Forms\Components\TextInput::make('password')
                                ->label('Confirm your password')
                                ->password()
                                ->required()
                                ->rules(['current_password']),
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for deletion (will be logged)')
                                ->required()
                                ->minLength(10)
                                ->rows(2)
                                ->placeholder('Explain why this reservation must be permanently deleted...'),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $relatedCounts = [
                                'guests' => $record->guests()->count(),
                                'room_assignments' => $record->roomAssignments()->count(),
                                'charges' => $record->charges()->count(),
                                'payments' => $record->payments()->count(),
                                'logs' => $record->logs()->count(),
                                'snapshots' => $record->checkInSnapshots()->count(),
                            ];

                            // Save a snapshot of the reservation data before deletion
                            $snapshot = $record->toArray();

                            // Write to force_deletion_logs table (persists after reservation is deleted)
                            ForceDeletionLog::create([
                                'reference_number' => $record->reference_number,
                                'guest_name' => $record->guest_name,
                                'status' => $record->status,
                                'check_in_date' => $record->check_in_date,
                                'check_out_date' => $record->check_out_date,
                                'reason' => $data['reason'],
                                'deleted_by' => auth()->id(),
                                'deleted_by_name' => auth()->user()->name,
                                'related_counts' => $relatedCounts,
                                'reservation_snapshot' => $snapshot,
                            ]);

                            // Also log to application log file as backup
                            Log::warning('FORCE DELETE RESERVATION', [
                                'reference_number' => $record->reference_number,
                                'guest_name' => $record->guest_name,
                                'status' => $record->status,
                                'deleted_by' => auth()->user()->name.' (ID: '.auth()->id().')',
                                'reason' => $data['reason'],
                                'related_counts' => $relatedCounts,
                            ]);

                            // Cascade delete all related records
                            $record->checkInSnapshots()->delete();
                            $record->roomAssignments()->delete();
                            $record->charges()->delete();
                            $record->payments()->delete();
                            $record->logs()->delete();
                            $record->guests()->delete();
                            $record->delete();

                            Notification::make()
                                ->title('Reservation force-deleted')
                                ->body("Reservation {$record->reference_number} has been permanently deleted and logged.")
                                ->warning()
                                ->send();
                        }),

                    // Copy Payment Link action
                    Tables\Actions\Action::make('refresh_payment_link')
                        ->label('Refresh Payment Link')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Reservation $record) => Setting::isOnlinePaymentsEnabled() &&
                            $record->canAcceptGuestPayment()
                        )
                        ->modalHeading('Refresh payment link')
                        ->modalDescription('Generate a fresh payment link for this room-held reservation and optionally email it to the guest.')
                        ->form(fn (Reservation $record) => [
                            Forms\Components\Placeholder::make('current_status')
                                ->label('Reservation Status')
                                ->content(str($record->status)->replace('_', ' ')->title()->toString()),
                            Forms\Components\Placeholder::make('current_expiry')
                                ->label('Current Link Expiry')
                                ->content($record->payment_link_expires_at
                                    ? $record->payment_link_expires_at->format('F j, Y \a\t g:i A')
                                    : 'Not set'
                                ),
                            Forms\Components\Toggle::make('email_guest')
                                ->label('Email refreshed link to guest')
                                ->default(fn () => filled($record->guest_email))
                                ->helperText(fn () => filled($record->guest_email)
                                    ? 'The guest will receive a new secure payment link by email.'
                                    : 'This reservation has no guest email on file, so only the link will be refreshed.'
                                ),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $result = app(ReservationWorkflowService::class)->refreshGuestPaymentLink(
                                $record,
                                (bool) ($data['email_guest'] ?? false)
                            );

                            $freshReservation = $result['reservation'];
                            $body = 'New payment link expires on '
                                .($freshReservation->payment_link_expires_at
                                    ? $freshReservation->payment_link_expires_at->format('F j, Y \a\t g:i A')
                                    : 'N/A')
                                .'.';

                            if ($result['emailed']) {
                                $body .= ' The guest has also been emailed the refreshed link.';
                            }

                            Notification::make()
                                ->title('Payment link refreshed')
                                ->body($body)
                                ->success()
                                ->send();
                        }),

                    // Copy Payment Link action
                    Tables\Actions\Action::make('copy_payment_link')
                        ->label('Copy Payment Link')
                        ->icon('heroicon-o-clipboard-document')
                        ->color('info')
                        ->visible(fn (Reservation $record) => Setting::isOnlinePaymentsEnabled() &&
                            $record->isPaymentLinkValid() &&
                            $record->canAcceptGuestPayment()
                        )
                        ->modalHeading('Payment Link & QR Code')
                        ->modalDescription('Share this payment link or show the QR code to the guest for easy payment.')
                        ->modalWidth('3xl')
                        ->form(fn (Reservation $record) => [
                            Forms\Components\Section::make('Payment Link')
                                ->schema([
                                    Forms\Components\TextInput::make('payment_link')
                                        ->label('Payment URL')
                                        ->default($record->generatePaymentLink())
                                        ->disabled()
                                        ->columnSpanFull()
                                        ->extraAttributes(['class' => 'font-mono'])
                                        ->suffixIcon('heroicon-o-clipboard-document')
                                        ->helperText('Click inside and press Ctrl+A then Ctrl+C to copy'),
                                    Forms\Components\Placeholder::make('expires_info')
                                        ->label('Link Expires')
                                        ->content($record->payment_link_expires_at ? $record->payment_link_expires_at->format('F j, Y \a\t g:i A') : 'N/A'),
                                ])->columns(2),
                            Forms\Components\Section::make('QR Code')
                                ->description('Guest can scan this QR code with their phone camera to access the payment page.')
                                ->schema([
                                    Forms\Components\Placeholder::make('qr_code')
                                        ->label('')
                                        ->content(fn (Reservation $record) => new HtmlString(
                                            '<div style="text-align:center;padding:20px;background:#f9fafb;border-radius:8px;">'
                                            .'<img src="'.e(route('guest.payment.qr', ['token' => $record->payment_link_token])).'" '
                                            .'alt="Payment QR Code" '
                                            .'style="max-width:300px;border:3px solid #00491E;border-radius:12px;padding:15px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);" />'
                                            .'<p style="margin-top:15px;color:#6b7280;font-size:14px;">Scan to pay with GCash, Maya, or Card</p>'
                                            .'</div>'
                                        )),
                                ]),
                            Forms\Components\Section::make('Sharing Options')
                                ->schema([
                                    Forms\Components\Placeholder::make('share_instructions')
                                        ->label('')
                                        ->content(new HtmlString(
                                            '<div style="color:#374151;font-size:14px;line-height:1.6;">'
                                            .'<p style="margin-bottom:12px;"><strong>How to share with the guest:</strong></p>'
                                            .'<ol style="margin-left:20px;margin-bottom:12px;">'
                                            .'<li><strong>Copy the link:</strong> Click the payment URL field above, press Ctrl+A then Ctrl+C, then paste it into SMS, WhatsApp, Messenger, or any messaging app.</li>'
                                            .'<li><strong>Show QR code:</strong> Show this screen to the guest so they can scan the QR code with their phone camera.</li>'
                                            .'<li><strong>Screenshot QR code:</strong> Take a screenshot (Windows+Shift+S) of the QR code and send the image to the guest.</li>'
                                            .'<li><strong>Print QR code:</strong> Right-click the QR code image -> "Save image as...", then print it to give to the guest.</li>'
                                            .'</ol>'
                                            .'<p style="color:#059669;"><strong>Tip:</strong> The QR code works great for walk-in guests or phone-based sharing!</p>'
                                            .'</div>'
                                        )),
                                ])->collapsed(),
                        ])
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->action(function (Reservation $record) {
                            // Log when modal is opened
                            ReservationLog::record(
                                $record,
                                'payment_link_viewed',
                                'Payment link viewed by '.auth()->user()->name.'.'
                            );
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    // Bulk Approve (admin+)
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn () => auth()->user()?->hasPermission('reservations_edit') ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Approve selected reservations')
                        ->modalDescription('Only reservations with status "Pending" will be approved. Others will be skipped.')
                        ->modalSubmitActionLabel('Approve')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'pending') {
                                    continue;
                                }
                                app(ReservationWorkflowService::class)->approve($record);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} reservation(s) approved")
                                ->success()
                                ->send();
                        }),

                    // Bulk Decline (admin+)
                    Tables\Actions\BulkAction::make('bulk_decline')
                        ->label('Decline selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn () => auth()->user()?->hasPermission('reservations_edit') ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Decline selected reservations')
                        ->modalDescription('Only reservations with status "Pending" will be declined. Others will be skipped.')
                        ->modalSubmitActionLabel('Decline')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Reason for declining')
                                ->placeholder('Optional reason...')
                                ->rows(2),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'pending') {
                                    continue;
                                }
                                app(ReservationWorkflowService::class)->decline($record, $data['admin_notes'] ?? null);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} reservation(s) declined")
                                ->success()
                                ->send();
                        }),

                    // Bulk Cancel (admin+)
                    Tables\Actions\BulkAction::make('bulk_cancel')
                        ->label('Cancel selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('warning')
                        ->visible(fn () => auth()->user()?->hasPermission('reservations_edit') ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Cancel selected reservations')
                        ->modalDescription('Only reservations with status "Pending" or "Approved" will be cancelled. Checked-in reservations will be skipped.')
                        ->modalSubmitActionLabel('Cancel reservations')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Reason for cancellation')
                                ->placeholder('Optional reason...')
                                ->rows(2),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (! in_array($record->status, ['pending', 'approved', 'confirmed'])) {
                                    continue;
                                }
                                app(ReservationWorkflowService::class)->cancel($record, $data['admin_notes'] ?? null);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} reservation(s) cancelled")
                                ->success()
                                ->send();
                        }),

                    // Bulk Force Checkout (super_admin only + password)
                    Tables\Actions\BulkAction::make('bulk_force_checkout')
                        ->label('Force checkout selected')
                        ->icon('heroicon-o-arrow-left-on-rectangle')
                        ->color('warning')
                        ->visible(fn () => auth()->user()->isSuperAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Force checkout selected reservations')
                        ->modalDescription('Only checked-in reservations will be checked out. This is intended for emergency or maintenance use. Enter your password and reason to confirm.')
                        ->modalSubmitActionLabel('Force checkout')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Forms\Components\TextInput::make('password')
                                ->label('Confirm your password')
                                ->password()
                                ->revealable()
                                ->required()
                                ->rule('current_password'),
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for force checkout')
                                ->placeholder('Example: Emergency maintenance, room closure, evacuation, or administrative correction...')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $checkedOut = 0;
                            $skipped = 0;
                            $failed = 0;
                            $remarks = 'Bulk force checkout: '.trim((string) ($data['reason'] ?? ''));

                            foreach ($records as $record) {
                                if ($record->status !== 'checked_in') {
                                    $skipped++;

                                    continue;
                                }

                                try {
                                    app(ReservationWorkflowService::class)->checkOut($record, now(), $remarks);
                                    $checkedOut++;
                                } catch (\Throwable $exception) {
                                    $failed++;

                                    Log::warning('Bulk force checkout failed for reservation '.$record->id, [
                                        'reservation_id' => $record->id,
                                        'reference_number' => $record->reference_number,
                                        'error' => $exception->getMessage(),
                                    ]);
                                }
                            }

                            $message = "{$checkedOut} reservation(s) force checked out";
                            if ($skipped > 0) {
                                $message .= ". {$skipped} non-checked-in reservation(s) skipped.";
                            }
                            if ($failed > 0) {
                                $message .= " {$failed} reservation(s) failed; check logs for details.";
                            }

                            $notification = Notification::make()->title($message);
                            if ($failed > 0) {
                                $notification->warning();
                            } else {
                                $notification->success();
                            }
                            $notification->send();
                        }),

                    // Bulk Delete (super_admin only + password)
                    Tables\Actions\BulkAction::make('bulk_delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn () => auth()->user()->isSuperAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected reservations')
                        ->modalDescription('This action is permanent and cannot be undone. Checked-in reservations will be skipped. Enter your password to confirm.')
                        ->modalSubmitActionLabel('Delete permanently')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Forms\Components\TextInput::make('password')
                                ->label('Confirm your password')
                                ->password()
                                ->revealable()
                                ->required()
                                ->rule('current_password'),
                        ])
                        ->action(function (Collection $records) {
                            $skipped = 0;
                            $deleted = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'checked_in') {
                                    $skipped++;

                                    continue;
                                }
                                $record->delete();
                                $deleted++;
                            }
                            $msg = "{$deleted} reservation(s) deleted";
                            if ($skipped > 0) {
                                $msg .= ". {$skipped} checked-in reservation(s) were skipped.";
                            }
                            Notification::make()
                                ->title($msg)
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->extremePaginationLinks()
            ->deferLoading() // Show loading skeleton while data loads
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession();
    }

    public static function getRelations(): array
    {
        return [
            ReservationResource\RelationManagers\RoomAssignmentsRelationManager::class,
            ReservationResource\RelationManagers\PaymentsRelationManager::class,
            ReservationResource\RelationManagers\StayLogsRelationManager::class,
        ];
    }

    protected static function makeApprovalRoomAssignmentSchema(Reservation $record): array
    {
        $checkIn = $record->check_in_date ? Carbon::parse($record->check_in_date) : null;
        $checkOut = $record->check_out_date ? Carbon::parse($record->check_out_date) : null;

        if (! $checkIn || ! $checkOut) {
            return [
                Forms\Components\Placeholder::make('room_assignment_dates_missing')
                    ->label('Available Rooms')
                    ->content('Check-in and check-out dates are required to filter available rooms.'),
            ];
        }

        return $record->getEffectiveRoomRequests()
            ->map(function ($requestLine) use ($checkIn, $checkOut) {
                $roomType = $requestLine->roomType;

                if (! $roomType) {
                    return null;
                }

                $availableRooms = app(RoomHoldService::class)
                    ->getAvailableRooms(
                        $roomType,
                        $checkIn,
                        $checkOut,
                        $requestLine->requested_capacity,
                        $roomType->isPrivate() || (int) $requestLine->requested_room_count > 1
                            ? null
                            : (int) $requestLine->occupant_count,
                    );
                $requestedRoomIds = collect((array) ($requestLine->requested_room_ids ?? []))
                    ->map(fn ($roomId): int => (int) $roomId)
                    ->filter()
                    ->values();
                $defaultRoomIds = $requestedRoomIds
                    ->intersect($availableRooms->pluck('id'))
                    ->take(max(1, (int) $requestLine->requested_room_count))
                    ->values()
                    ->all();
                $requestedRooms = max(1, (int) $requestLine->requested_room_count);
                $occupants = max(1, (int) $requestLine->occupant_count);
                $options = $availableRooms
                    ->mapWithKeys(function (Room $room) use ($roomType, $checkIn, $checkOut): array {
                        $label = $room->room_number.($room->floor?->name ? ' - '.$room->floor->name : '');
                        if (! $roomType->isPrivate()) {
                            $beds = app(RoomHoldService::class)->availableSlotsForDates($room, $checkIn, $checkOut);
                            $label .= " — {$beds} bed".($beds === 1 ? '' : 's').' available';
                        }

                        return [$room->id => $label];
                    })
                    ->all();

                $capacityLabel = $requestLine->requested_capacity ? " — up to {$requestLine->requested_capacity} guests" : '';

                return Forms\Components\Select::make("assigned_room_ids_by_type.request_{$requestLine->id}")
                    ->label("{$roomType->name}{$capacityLabel} ({$requestedRooms} room(s), {$occupants} guest(s))")
                    ->multiple()
                    ->required()
                    ->minItems($requestedRooms)
                    ->maxItems($requestedRooms)
                    ->maxItemsMessage("Select exactly {$requestedRooms} room(s) for this request.")
                    ->searchable()
                    ->preload()
                    ->default($defaultRoomIds)
                    ->options($options)
                    ->rules([
                        function () use ($roomType, $checkIn, $checkOut, $occupants): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail) use ($roomType, $checkIn, $checkOut, $occupants): void {
                                if ($roomType->isPrivate() || ! is_array($value) || $value === []) {
                                    return;
                                }

                                $rooms = Room::query()->with('roomType')->whereIn('id', $value)->get();
                                if (! app(RoomHoldService::class)->selectedDormRoomsCanAccommodate($rooms, $checkIn, $checkOut, $occupants)) {
                                    $available = $rooms->sum(fn (Room $room): int => app(RoomHoldService::class)->availableSlotsForDates($room, $checkIn, $checkOut));
                                    $fail("The selected dorm rooms have {$available} available bed(s), but {$occupants} guest(s) need accommodation.");
                                }
                            };
                        },
                    ])
                    ->helperText($availableRooms->isEmpty()
                        ? 'No available rooms for this date range.'
                        : 'Showing rooms available from '.$checkIn->format('M d, Y').' to '.$checkOut->format('M d, Y').(! $roomType->isPrivate() ? '. Dorm labels show remaining beds.' : '')
                    );
            })
            ->filter()
            ->values()
            ->all();
    }

    protected static function makeAlternativeOfferSchema(Reservation $record): array
    {
        $checkIn = Carbon::parse($record->check_in_date);
        $checkOut = Carbon::parse($record->check_out_date);
        // Older reservations predate room-request lines. Persist their
        // equivalent line so the offer action receives a valid request ID.
        $requests = $record->ensureRoomRequests()->loadMissing('roomType');

        return [
            Forms\Components\Select::make('reservation_room_request_id')
                ->label('Unavailable requested room type')
                ->options($requests->mapWithKeys(fn ($line) => [
                    $line->id => ($line->roomType?->name ?? 'Unknown').' ('.$line->requested_room_count.' room(s), '.$line->occupant_count.' guest(s))',
                ])->all())
                ->required()
                ->live(),
            Forms\Components\Select::make('offered_room_type_id')
                ->label('Alternative room type')
                ->options(function (Get $get) use ($requests, $checkIn, $checkOut): array {
                    $requestLine = $requests->firstWhere('id', (int) $get('reservation_room_request_id'));
                    if (! $requestLine) return [];

                    return RoomType::query()->where('is_active', true)->get()
                        ->filter(function (RoomType $roomType) use ($requestLine, $checkIn, $checkOut): bool {
                            return app(RoomHoldService::class)->canAccommodateRoomRequest(
                                $roomType,
                                $checkIn,
                                $checkOut,
                                (int) $requestLine->occupant_count,
                                (int) $requestLine->requested_room_count,
                            );
                        })
                        ->mapWithKeys(fn (RoomType $roomType) => [$roomType->id => $roomType->name.' - PHP '.number_format((float) $roomType->base_rate, 2).' per night'])
                        ->all();
                })
                ->required()
                ->live(),
            Forms\Components\Select::make('room_ids')
                ->label('Rooms to hold for 24 hours')
                ->multiple()
                ->searchable()
                ->options(function (Get $get) use ($checkIn, $checkOut): array {
                    $roomType = RoomType::find($get('offered_room_type_id'));
                    if (! $roomType) return [];

                    return app(RoomHoldService::class)->getAvailableRooms($roomType, $checkIn, $checkOut)
                        ->mapWithKeys(fn (Room $room) => [$room->id => $room->room_number.($room->floor?->name ? ' - '.$room->floor->name : '')])
                        ->all();
                })
                ->required()
                ->minItems(fn (Get $get): int => max(1, (int) ($requests->firstWhere('id', (int) $get('reservation_room_request_id'))?->requested_room_count ?? 1)))
                ->maxItems(fn (Get $get): int => max(1, (int) ($requests->firstWhere('id', (int) $get('reservation_room_request_id'))?->requested_room_count ?? 1)))
                ->helperText(fn (Get $get): string => 'Select exactly '.max(1, (int) ($requests->firstWhere('id', (int) $get('reservation_room_request_id'))?->requested_room_count ?? 1)).' available room(s) for this stay.'),
            Forms\Components\Textarea::make('message')->label('Message to guest (optional)')->rows(3),
        ];
    }

    /**
     * Compute total add-ons cost from [{code, qty}] items.
     * Also handles legacy format (plain array of code strings) for backward compatibility.
     */
    protected static function computeAddonsTotal(array $items): float
    {
        if (empty($items)) {
            return 0.0;
        }
        // Normalize: detect legacy format (array of plain strings)
        if (isset($items[0]) && is_string($items[0])) {
            $items = array_map(fn ($code) => ['code' => $code, 'qty' => 1], array_filter($items));
        }
        $items = collect($items)->filter(fn ($i) => ! empty($i['code'] ?? null));
        if ($items->isEmpty()) {
            return 0.0;
        }
        $services = Service::whereIn('code', $items->pluck('code')->unique()->values())->get()->keyBy('code');

        return (float) $items->sum(fn ($i) => (float) ($services->get($i['code'])?->price ?? 0) * max(1, (int) ($i['qty'] ?? 1))
        );
    }

    protected static function recalculateDiscountedTotal($set, $get, $record): void
    {
        if (! $record) {
            return;
        }

        $nights = max(1, $record->check_in_date->diffInDays($record->check_out_date));

        $assignments = $record->roomAssignments()->with('room.roomType')->get()->unique('room_id');
        $roomCharges = 0;
        foreach ($assignments as $assignment) {
            if ($assignment->room && $assignment->room->roomType) {
                $rate = (float) $assignment->room->roomType->base_rate;
                $roomCharges += $rate * $nights;
            }
        }

        $addonsTotal = static::computeAddonsTotal($get('checkin_additional_requests') ?? []);

        $subtotal = $roomCharges + $addonsTotal;

        $isPwd = (bool) ($get('checkin_is_pwd') ?? false);
        $isSenior = (bool) ($get('checkin_is_senior_citizen') ?? false);
        $isStudent = (bool) ($get('checkin_is_student') ?? false);

        $pwdPercent = (float) Setting::get('discount_pwd_percent', 0);
        $seniorPercent = (float) Setting::get('discount_senior_percent', 0);
        $studentPercent = (float) Setting::get('discount_student_percent', 0);

        // Pick only the highest applicable discount
        $candidates = [];
        if ($isPwd && $pwdPercent > 0) {
            $candidates[] = $pwdPercent;
        }
        if ($isSenior && $seniorPercent > 0) {
            $candidates[] = $seniorPercent;
        }
        if ($isStudent && $studentPercent > 0) {
            $candidates[] = $studentPercent;
        }

        $totalDiscountPercent = ! empty($candidates) ? max($candidates) : 0;
        $totalDiscountPercent = min($totalDiscountPercent, 100);
        $discountAmount = ($subtotal * $totalDiscountPercent) / 100;
        $newTotal = max(0, $subtotal - $discountAmount);

        $set('checkin_payment_amount', round($newTotal, 2));
    }

    public static function computeCheckInPricing(
        Reservation $record,
        array $reservationRooms,
        mixed $checkInState,
        mixed $checkOutState,
        array $additionalRequests,
        bool $isPwd = false,
        bool $isSenior = false,
        bool $isStudent = false
    ): array {
        // Always use date-only difference (no time component) for nights calculation
        $checkIn = $checkInState ? Carbon::parse($checkInState)->startOfDay() : Carbon::parse($record->check_in_date)->startOfDay();
        $checkOut = $checkOutState ? Carbon::parse($checkOutState)->startOfDay() : Carbon::parse($record->check_out_date)->startOfDay();
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
            $companionCount = count($entry['guests'] ?? []);
            $guestCount = $companionCount + ((bool) ($entry['includes_primary_guest'] ?? false) ? 1 : 0);
            $rate = (float) $roomType->base_rate;
            $roomMode = $entry['room_mode'] ?? ($roomType->isPrivate() ? 'private' : 'dorm');

            // Match pricing basis to selected allocation mode in the check-in form.
            $isPerBedPricing = $roomMode === 'dorm';

            if ($isPerBedPricing) {
                $lineTotal = $rate * $guestCount * $nights;
                $formula = sprintf(
                    'PHP %s x %d guest(s) x %d night(s) = PHP %s',
                    number_format($rate, 2),
                    $guestCount,
                    $nights,
                    number_format($lineTotal, 2)
                );
            } else {
                $lineTotal = $rate * $nights;
                $formula = sprintf(
                    'PHP %s x %d night(s) = PHP %s',
                    number_format($rate, 2),
                    $nights,
                    number_format($lineTotal, 2)
                );
            }

            $roomSubtotal += $lineTotal;
            $roomLines[] = [
                'label' => "Room {$room->room_number} ({$roomType->name}, ".ucfirst($roomMode).')',
                'formula' => $formula,
                'line_total' => $lineTotal,
            ];
        }

        $servicesTotal = static::computeAddonsTotal($additionalRequests);

        $subtotal = $roomSubtotal + $servicesTotal;

        // Calculate discount - pick only the highest applicable discount
        $pwdPercent = (float) Setting::get('discount_pwd_percent', 0);
        $seniorPercent = (float) Setting::get('discount_senior_percent', 0);
        $studentPercent = (float) Setting::get('discount_student_percent', 0);

        $candidates = [];
        if ($isPwd && $pwdPercent > 0) {
            $candidates[] = $pwdPercent;
        }
        if ($isSenior && $seniorPercent > 0) {
            $candidates[] = $seniorPercent;
        }
        if ($isStudent && $studentPercent > 0) {
            $candidates[] = $studentPercent;
        }

        $totalDiscountPercent = ! empty($candidates) ? max($candidates) : 0;
        $totalDiscountPercent = min($totalDiscountPercent, 100);
        $discountAmount = ($subtotal * $totalDiscountPercent) / 100;
        $grandTotal = max(0, $subtotal - $discountAmount);

        // Fallback to declared reservation pricing when no room lines are available yet.
        if (empty($roomLines)) {
            $declaredBase = (float) $record->preferredRoomType->calculateRate($nights, (int) $record->number_of_occupants);
            $declaredSubtotal = $declaredBase + $servicesTotal;
            $declaredDiscount = ($declaredSubtotal * $totalDiscountPercent) / 100;
            $declaredGrandTotal = max(0, $declaredSubtotal - $declaredDiscount);

            return [
                'nights' => $nights,
                'rooms' => [],
                'room_subtotal' => $declaredBase,
                'services_total' => $servicesTotal,
                'subtotal' => $declaredSubtotal,
                'discount_percent' => $totalDiscountPercent,
                'discount_amount' => $declaredDiscount,
                'grand_total' => $declaredGrandTotal,
            ];
        }

        return [
            'nights' => $nights,
            'rooms' => $roomLines,
            'room_subtotal' => $roomSubtotal,
            'services_total' => $servicesTotal,
            'subtotal' => $subtotal,
            'discount_percent' => $totalDiscountPercent,
            'discount_amount' => $discountAmount,
            'grand_total' => $grandTotal,
        ];
    }



    /**
     * @return array{guest_name:string,payment_mode:string,payment_amount:float,or_number:?string,online_reference:?string,or_date:mixed,addons_label:string,discount_applied:bool,discount_label:string,discount_amount:float}
     */
    protected static function resolveBillingSnapshot(Reservation $record): array
    {
        $paidAssignment = $record->roomAssignments()
            ->whereNotNull('payment_amount')
            ->latest('id')
            ->first();
        $latestPayment = $record->payments()
            ->where('status', 'posted')
            ->latest('id')
            ->first();

        $paymentModeRaw = $latestPayment?->payment_mode
            ?? $paidAssignment?->payment_mode
            ?? '';
        $paymentMode = $paymentModeRaw !== ''
            ? ucwords(str_replace('_', ' ', $paymentModeRaw))
            : '-';

        $addonsFromLedger = $record->charges()
            ->where('charge_type', 'addon')
            ->pluck('description')
            ->filter()
            ->values();

        $discountCharges = $record->charges()
            ->where('charge_type', 'discount')
            ->get(['amount', 'meta']);

        $discountTotal = (float) abs($discountCharges->sum('amount'));

        $discountTypes = $discountCharges
            ->flatMap(function ($charge) {
                $types = data_get($charge->meta, 'discount_types', []);
                if (is_array($types) && ! empty($types)) {
                    return $types;
                }
                // Fallback: legacy 'discount_type' (singular string)
                $legacy = data_get($charge->meta, 'discount_type');

                return $legacy ? [(string) $legacy] : [];
            })
            ->filter()
            ->map(fn ($type) => self::formatDiscountBadgeLabel((string) $type))
            ->filter()
            ->unique()
            ->values();

        $additionalRequests = $paidAssignment?->additional_requests ?? [];

        $billingGuestName = $record->billingGuest?->displayName() ?? '';

        $paymentAmount = (float) ($latestPayment?->amount
            ?? $paidAssignment?->payment_amount
            ?? 0);
        if ($paymentAmount <= 0 && (float) ($record->payments_total ?? 0) > 0) {
            $paymentAmount = (float) $record->payments_total;
        }

        $discountLabel = '-';
        if ($discountTypes->isNotEmpty()) {
            $discountLabel = $discountTypes->implode(', ');
        }

        $discountApplied = $discountTotal > 0 || $discountTypes->isNotEmpty();

        $officialOrNumber = $paidAssignment?->payment_or_number;
        $officialOrNumber = filled($officialOrNumber) && strtoupper(trim((string) $officialOrNumber)) !== 'N/A'
            ? trim((string) $officialOrNumber)
            : null;
        $onlineReference = $latestPayment?->gateway === 'paymongo'
            ? ($latestPayment->reference_no ?: ($latestPayment->gateway_payment_id ? 'PM-'.$latestPayment->gateway_payment_id : null))
            : null;

        return [
            'guest_name' => $billingGuestName !== ''
                ? $billingGuestName
                : ($paidAssignment
                    ? trim(($paidAssignment->guest_first_name ?? '').' '.($paidAssignment->guest_last_name ?? ''))
                    : (string) $record->guest_name),
            'payment_mode' => $paymentMode,
            'payment_amount' => $paymentAmount,
            'or_number' => $officialOrNumber,
            'online_reference' => $onlineReference,
            'or_date' => $latestPayment?->or_date
                ?? $paidAssignment?->or_date,
            'discount_applied' => $discountApplied,
            'discount_label' => $discountApplied ? $discountLabel : 'No',
            'discount_amount' => $discountTotal,
            'addons_label' => $addonsFromLedger->isNotEmpty()
                ? $addonsFromLedger->implode(', ')
                : self::formatServiceCodes(is_array($additionalRequests) ? $additionalRequests : []),
        ];
    }

    protected static function formatDiscountBadgeLabel(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (preg_match('/^(?<label>.+?)\s*\((?<percent>\d+(?:\.\d+)?)%\)$/i', $value, $matches)) {
            $label = self::formatDiscountBadgeBaseLabel($matches['label']);
            $percent = rtrim(rtrim($matches['percent'], '0'), '.');

            return "{$label} ({$percent}%)";
        }

        $label = self::formatDiscountBadgeBaseLabel($value);

        return match (strtolower($label)) {
            'pwd' => self::appendDiscountPercent($label, (float) Setting::get('discount_pwd_percent', 0)),
            'senior citizen' => self::appendDiscountPercent($label, (float) Setting::get('discount_senior_percent', 0)),
            'student' => self::appendDiscountPercent($label, (float) Setting::get('discount_student_percent', 0)),
            default => $label,
        };
    }

    protected static function formatDiscountBadgeBaseLabel(string $label): string
    {
        $normalized = strtolower(trim($label));

        return match ($normalized) {
            'pwd' => 'PWD',
            'senior citizen' => 'Senior Citizen',
            'student' => 'Student',
            default => ucwords($normalized),
        };
    }

    protected static function appendDiscountPercent(string $label, float $percent): string
    {
        if ($percent <= 0) {
            return $label;
        }

        $formattedPercent = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return "{$label} ({$formattedPercent}%)";
    }

    /**
     * @return array{id_type:?string,id_number:?string,nationality:?string,purpose:?string,arrival_at:?string,scheduled_checkout_at:?string,remarks:?string}
     */
    protected static function resolveCheckInSnapshot(Reservation $record): array
    {
        $assignment = $record->roomAssignments()->latest('checked_in_at')->first();
        $snapshot = $record->checkInSnapshots()->latest('id')->first();
        // Date & Time of Arrival = staff-entered detailed_checkin_datetime
        $arrivalAtRaw = $snapshot?->detailed_checkin_datetime
            ?? $assignment?->detailed_checkin_datetime;
        // Scheduled Check-out = staff-entered detailed_checkout_datetime
        $scheduledCheckoutRaw = $snapshot?->detailed_checkout_datetime
            ?? $assignment?->detailed_checkout_datetime;

        return [
            'id_type' => $snapshot?->id_type
                ?? $assignment?->id_type,
            'id_number' => $snapshot?->id_number
                ?? $assignment?->id_number,
            'nationality' => $snapshot?->nationality
                ?? $assignment?->nationality
                ?? 'Filipino',
            'purpose' => $snapshot?->purpose_of_stay
                ?? $assignment?->purpose_of_stay
                ?? ucwords(str_replace('_', ' ', (string) $record->purpose)),
            'checkin_at' => $arrivalAtRaw
                ? Carbon::parse($arrivalAtRaw)->format('M d, Y')
                : null,
            'checkout_at' => $scheduledCheckoutRaw
                ? Carbon::parse($scheduledCheckoutRaw)->format('M d, Y')
                : null,
            'remarks' => $snapshot?->remarks
                ?? $assignment?->remarks,
        ];
    }

    /**
     * @param  array<int,string|array>  $serviceCodes
     */
    protected static function formatServiceCodes(array $serviceCodes): string
    {
        if (empty($serviceCodes)) {
            return 'No add-ons selected';
        }

        // Detect new format [{code,qty}] vs legacy format [code, ...]
        if (isset($serviceCodes[0]) && is_array($serviceCodes[0])) {
            $items = collect($serviceCodes)->filter(fn ($i) => ! empty($i['code'] ?? null));
            $addons = Service::whereIn('code', $items->pluck('code')->unique())->get()->keyBy('code');
            $names = $items->map(function ($item) use ($addons) {
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $service = $addons->get($item['code']);
                if (! $service) {
                    return null;
                }
                $linePrice = $qty > 1 ? 'PHP '.number_format((float) $service->price * $qty, 2) : null;
                $label = $service->name.($service->price > 0
                    ? ' ('.($linePrice ?? 'PHP '.number_format((float) $service->price, 2)).')'
                    : ' (Free)');

                return $qty > 1 ? "{$qty}x {$label}" : $label;
            })->filter()->values();
        } else {
            $names = Service::whereIn('code', array_filter($serviceCodes))
                ->get()
                ->map(fn (Service $service) => $service->name.($service->price > 0 ? ' (PHP '.number_format((float) $service->price, 2).')' : ' (Free)'))
                ->values();
        }

        return $names->isEmpty() ? 'No add-ons selected' : $names->implode(', ');
    }

    /**
     * @return array<int, string>
     */
    public static function directAssignmentCapacityOptions(mixed $roomTypeId): array
    {
        if (! is_numeric($roomTypeId) || ! ($roomType = RoomType::find((int) $roomTypeId))) {
            return [];
        }

        return collect(app(RoomHoldService::class)->getSellableCapacities($roomType))
            ->mapWithKeys(fn (int $capacity): array => [$capacity => "Up to {$capacity} guests"])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function directAssignmentRoomOptions(
        mixed $roomTypeId,
        mixed $capacity,
        mixed $checkInDate,
        mixed $checkOutDate,
    ): array {
        if (! is_numeric($roomTypeId) || ! is_numeric($capacity) || blank($checkInDate) || blank($checkOutDate)) {
            return [];
        }

        try {
            $checkIn = Carbon::parse($checkInDate)->startOfDay();
            $checkOut = Carbon::parse($checkOutDate)->startOfDay();
        } catch (\Throwable) {
            return [];
        }

        if ($checkOut->lte($checkIn) || ! ($roomType = RoomType::find((int) $roomTypeId))) {
            return [];
        }

        return app(RoomHoldService::class)
            ->getAvailableRooms($roomType, $checkIn, $checkOut, (int) $capacity)
            ->mapWithKeys(fn (Room $room): array => [
                $room->id => $room->room_number.($room->floor?->name ? ' - '.$room->floor->name : ''),
            ])
            ->all();
    }

    public static function directAssignmentOccupantHelp(mixed $roomTypeId, mixed $capacity, mixed $roomIds): string
    {
        $roomCount = count(array_filter((array) $roomIds));

        if (! is_numeric($roomTypeId) || ! ($roomType = RoomType::find((int) $roomTypeId))) {
            return 'Select a room type, capacity, and rooms first.';
        }

        if (! $roomType->isPrivate()) {
            return 'Guests will be allocated across the selected shared rooms according to available beds.';
        }

        if (! is_numeric($capacity) || $roomCount < 1) {
            return 'Select the rooms first to see the guest limit.';
        }

        $maximum = max(1, (int) $capacity) * $roomCount;

        return "Up to {$maximum} guests across {$roomCount} selected room".($roomCount === 1 ? '.' : 's.');
    }

    public static function directAssignmentMaximumOccupants(mixed $roomTypeId, mixed $capacity, mixed $roomIds): int
    {
        if (! is_numeric($roomTypeId) || ! ($roomType = RoomType::find((int) $roomTypeId)) || ! $roomType->isPrivate()) {
            return 200;
        }

        if (! is_numeric($capacity)) {
            return 200;
        }

        $roomCount = count(array_filter((array) $roomIds));

        return $roomCount > 0 ? max(1, (int) $capacity) * $roomCount : 200;
    }

    /**
     * Match the guest reservation form's client-side occupant limit for a
     * single room. Public/shared room types continue to use the form-wide
     * limit; private rooms use the selected room type's actual capacity.
     */
    protected static function maximumOccupantsForRoomType(mixed $roomTypeId): int
    {
        if (! is_numeric($roomTypeId)) {
            return 20;
        }

        $roomType = RoomType::find((int) $roomTypeId);

        if (! $roomType || $roomType->room_sharing_type === 'public') {
            return 20;
        }

        return min(20, max(1, (int) $roomType->capacity));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'view' => Pages\ViewReservation::route('/{record}'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
            'check-in' => Pages\CheckInGuest::route('/{record}/check-in'),
        ];
    }
}
