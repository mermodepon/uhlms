<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\ForceDeletionLog;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Service;
use App\Models\Setting;
use App\Services\CheckInService;
use App\Services\RoomHoldService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
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
                        Forms\Components\TextInput::make('guest_last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_middle_initial')
                            ->label('Middle Initial')
                            ->maxLength(10)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_age')
                            ->label('Age')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\TextInput::make('guest_phone')
                            ->maxLength(30)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\Select::make('guest_gender')
                            ->label('Gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                            ])
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\Textarea::make('guest_address')
                            ->rows(2)
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
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\DatePicker::make('check_in_date')
                            ->required()
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\DatePicker::make('check_out_date')
                            ->required()
                            ->after('check_in_date')
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
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
                            ])
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\Textarea::make('special_requests')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
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
                            ->required()
                            ->disabled(fn ($record) => $record && $record->status === 'checked_in'),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Staff Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Check-In Details')
                    ->description('Edit payment, add-ons, and identification details captured during check-in.')
                    ->visible(fn ($record) => $record && in_array($record->status, ['checked_in', 'checked_out'], true))
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
                                        $roomLines[] = "Room {$assignment->room->room_number}: ₱".number_format($rate, 2)." × {$nights} night(s) = ₱".number_format($lineTotal, 2);
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
                                            $addonLines[] = "{$qty}x {$addon->name}: ₱".number_format($lineTotal, 2);
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

                                $html .= '<div class="mt-2"><strong>Room Subtotal:</strong> ₱'.number_format($roomCharges, 2).'</div>';
                                $html .= '<div><strong>Add-Ons Total:</strong> ₱'.number_format($addonsTotal, 2).'</div>';
                                $html .= '<div><strong>Subtotal:</strong> ₱'.number_format($subtotal, 2).'</div>';

                                if (! empty($discountLines)) {
                                    $html .= '<div class="mt-2 text-green-600"><strong>Discounts Applied:</strong></div>';
                                    $html .= '<ul class="list-disc pl-5 text-green-600">';
                                    foreach ($discountLines as $line) {
                                        $html .= '<li>'.e($line).'</li>';
                                    }
                                    $html .= '</ul>';
                                    $html .= '<div class="text-green-600"><strong>Total Discount ('.$totalDiscountPercent.'%):</strong> -₱'.number_format($discountAmount, 2).'</div>';
                                }

                                $html .= '<div class="font-semibold text-lg mt-2"><strong>Grand Total:</strong> ₱'.number_format($grandTotal, 2).'</div>';
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
                            ->prefix('₱')
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
                                        Infolists\Components\TextEntry::make('billing_or_number')
                                            ->label('Official Receipt Number')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['or_number'] ?? '-')
                                            ->copyable(),
                                        Infolists\Components\TextEntry::make('billing_or_date')
                                            ->label('OR Date')
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
                                                ? '-₱'.number_format((float) self::resolveBillingSnapshot($record)['discount_amount'], 2)
                                                : '-'),
                                        Infolists\Components\TextEntry::make('billing_addons')
                                            ->label('Add-Ons')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['addons_label'])
                                            ->columnSpanFull()
                                            ->badge(),
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
                                            ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucfirst((string) $state)))
                                            ->color(fn ($state) => match ((string) $state) {
                                                'pending' => 'warning',
                                                'approved' => 'info',
                                                'confirmed' => 'primary',
                                                'pending_payment' => 'warning',
                                                'declined' => 'danger',
                                                'cancelled' => 'gray',
                                                'checked_in' => 'success',
                                                'checked_out' => 'gray',
                                                default => 'gray',
                                            }),
                                        Infolists\Components\TextEntry::make('checked_in_guests')
                                            ->label('Checked-In Guests')
                                            ->default(function (Reservation $record) {
                                                $checkedIn = $record->roomAssignments()->whereNotNull('checked_in_at')->count();

                                                return $checkedIn.' / '.((int) $record->number_of_occupants ?: $checkedIn);
                                            }),
                                        Infolists\Components\TextEntry::make('checkin_hold_expires_at')
                                            ->label('Payment Hold Expires')
                                            ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Carbon::parse($state)->format('M d, Y h:i A') : null)
                                            ->placeholder('No active hold'),
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
                                                // Try hold creator
                                                if ($record->checkin_hold_by && $record->checkin_hold_by > 0 && $record->checkin_hold_by !== null) {
                                                    $user = \App\Models\User::find($record->checkin_hold_by);
                                                    if ($user) {
                                                        return $user->name;
                                                    }
                                                }

                                                return '-';
                                            }),
                                    ])->columns(4),

                                Infolists\Components\Section::make('Captured Check-In Snapshot')
                                    ->description('Identity and stay details saved during prepare/finalize check-in.')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('snapshot_discount_availed')
                                            ->label('Discount Availed')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_label'])
                                            ->badge()
                                            ->color(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_applied'] ? 'success' : 'gray'),
                                        Infolists\Components\TextEntry::make('snapshot_discount_amount')
                                            ->label('Discount Amount')
                                            ->default(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_applied']
                                                ? '-₱'.number_format((float) self::resolveBillingSnapshot($record)['discount_amount'], 2)
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
                                    ->description('Secure payment link for guest deposit payment via GCash, Maya, or Card.')
                                    ->visible(fn (Reservation $record) => Setting::isOnlinePaymentsEnabled() &&
                                        $record->payment_link_token &&
                                        in_array($record->status, ['pending', 'approved', 'confirmed', 'pending_payment', 'checked_in'])
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
                                            ->default(fn (Reservation $record) => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($record->generatePaymentLink())
                                            )
                                            ->height(200)
                                            ->extraAttributes(['style' => 'border: 2px solid #e5e7eb; border-radius: 8px; padding: 8px;'])
                                            ->visible(fn (Reservation $record) => $record->isPaymentLinkValid()),
                                    ])->columns(2),

                                // Deposit status section — shown whenever gateway payments exist, regardless of toggle
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
                                                    'paid' => '✓ Deposit Paid Online — ₱'.number_format($gatewayPayment->amount, 2),
                                                    'pending' => '⏳ Payment Pending',
                                                    'failed' => '✗ Payment Failed',
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
                                                Infolists\Components\TextEntry::make('hold_type')
                                                    ->label('Type')
                                                    ->badge()
                                                    ->formatStateUsing(fn (string $state) => match ($state) {
                                                        'advance' => 'Advance',
                                                        'short_term' => 'Short-term',
                                                        default => $state,
                                                    })
                                                    ->color(fn (string $state) => match ($state) {
                                                        'advance' => 'success',
                                                        'short_term' => 'warning',
                                                        default => 'gray',
                                                    }),
                                                Infolists\Components\TextEntry::make('expires_at')
                                                    ->label('Expires At')
                                                    ->dateTime('M d, Y h:i A')
                                                    ->placeholder('No expiry (advance hold)')
                                                    ->color(fn (?string $state) => $state && \Carbon\Carbon::parse($state)->isPast() ? 'danger' : 'gray'),
                                            ])
                                            ->columns(5)
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
                Tables\Columns\TextColumn::make('preferredRoomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->width('130px')
                    ->getStateUsing(function (Reservation $record) {
                        // Show actual room type from assignment when checked in/out
                        if (in_array($record->status, ['checked_in', 'checked_out', 'pending_payment'], true)) {
                            $actualType = $record->roomAssignments
                                ->pluck('room.roomType.name')
                                ->filter()
                                ->unique()
                                ->implode(', ');

                            if (filled($actualType)) {
                                return $actualType;
                            }
                        }

                        return $record->preferredRoomType?->name;
                    }),
                Tables\Columns\TextColumn::make('room_display')
                    ->label('Room')
                    ->badge()
                    ->width('120px')
                    ->getStateUsing(function (Reservation $record) {
                        // Priority 1: Actual room assignments (for checked-in/out/pending_payment)
                        if (in_array($record->status, ['checked_in', 'checked_out', 'pending_payment'], true)) {
                            $assignedRooms = $record->roomAssignments
                                ->pluck('room.room_number')
                                ->filter()
                                ->unique()
                                ->values()
                                ->toArray();

                            if (! empty($assignedRooms)) {
                                return count($assignedRooms) === 1 ? $assignedRooms[0] : $assignedRooms;
                            }

                            // Fallback for pending_payment: check hold payload
                            if ($record->status === 'pending_payment') {
                                $holdPayload = $record->checkin_hold_payload ?? [];
                                $entries = data_get($holdPayload, 'entries', []);
                                $roomIds = collect($entries)->pluck('room_id')->filter()->unique();

                                if ($roomIds->isNotEmpty()) {
                                    $heldRooms = \App\Models\Room::whereIn('id', $roomIds)
                                        ->pluck('room_number')
                                        ->toArray();

                                    if (! empty($heldRooms)) {
                                        return array_map(fn ($r) => "🔒 {$r}", $heldRooms);
                                    }
                                }
                            }
                        }

                        // Priority 2: Advance holds (for approved/confirmed)
                        if (in_array($record->status, ['approved', 'confirmed'], true)) {
                            $holds = $record->roomHolds()
                                ->advance()
                                ->with('room')
                                ->get();

                            if ($holds->isNotEmpty()) {
                                $heldRooms = $holds
                                    ->pluck('room.room_number')
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->toArray();

                                if (! empty($heldRooms)) {
                                    // Add lock emoji to indicate these are holds, not actual occupancy
                                    $displayRooms = array_map(fn ($r) => "🔒 {$r}", $heldRooms);

                                    return count($displayRooms) === 1 ? $displayRooms[0] : $displayRooms;
                                }
                            }
                        }

                        return null;
                    })
                    ->color(function ($state, Reservation $record) {
                        // Green for actual occupancy, blue for holds
                        if (in_array($record->status, ['checked_in', 'checked_out'], true)) {
                            return 'success';
                        }
                        if (in_array($record->status, ['approved', 'confirmed', 'pending_payment'], true)) {
                            return 'info';
                        }

                        return 'gray';
                    })
                    ->tooltip(function ($state, Reservation $record) {
                        if (in_array($record->status, ['checked_in', 'checked_out'], true)) {
                            return 'Room assignment (occupied)';
                        }
                        if (in_array($record->status, ['approved', 'confirmed'], true)) {
                            return 'Advance hold (reserved)';
                        }
                        if ($record->status === 'pending_payment') {
                            return 'Short-term hold (payment pending)';
                        }

                        return null;
                    })
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

                        // Still checked in — show scheduled checkout as a deadline
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
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn ($state, $record): string => match (true) {
                        $state === 'pending' => 'warning',
                        $state === 'approved' && $record->roomAssignments->isEmpty() => 'info',
                        $state === 'approved' => 'primary',
                        $state === 'confirmed' => 'primary',
                        $state === 'pending_payment' => 'warning',
                        $state === 'declined' => 'danger',
                        $state === 'cancelled' => 'gray',
                        $state === 'checked_in' => 'success',
                        $state === 'checked_out' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_gateway_status')
                    ->label('Payment')
                    ->badge()
                    ->getStateUsing(function (Reservation $record) {
                        // Check for full payment (is_deposit = false)
                        $fullPayment = $record->payments()
                            ->where('gateway', 'paymongo')
                            ->where('is_deposit', false)
                            ->where('gateway_status', 'paid')
                            ->exists();

                        if ($fullPayment) {
                            return 'Online: Fully Paid';
                        }

                        // Check for deposit payment (is_deposit = true)
                        $depositPayment = $record->payments()
                            ->where('gateway', 'paymongo')
                            ->where('is_deposit', true)
                            ->where('gateway_status', 'paid')
                            ->exists();

                        if ($depositPayment) {
                            return 'Online: Deposit Paid';
                        }

                        // Check for pending payments
                        $pendingPayment = $record->payments()
                            ->where('gateway', 'paymongo')
                            ->where('gateway_status', 'pending')
                            ->exists();

                        if ($pendingPayment) {
                            return 'Online: Pending';
                        }

                        return 'Manual';
                    })
                    ->color(function (Reservation $record) {
                        // Full payment = green
                        $fullPayment = $record->payments()
                            ->where('gateway', 'paymongo')
                            ->where('is_deposit', false)
                            ->where('gateway_status', 'paid')
                            ->exists();

                        if ($fullPayment) {
                            return 'success';
                        }

                        // Deposit payment = yellow/warning
                        $depositPayment = $record->payments()
                            ->where('gateway', 'paymongo')
                            ->where('is_deposit', true)
                            ->where('gateway_status', 'paid')
                            ->exists();

                        if ($depositPayment) {
                            return 'warning';
                        }

                        // Pending = blue/info
                        $pendingPayment = $record->payments()
                            ->where('gateway', 'paymongo')
                            ->where('gateway_status', 'pending')
                            ->exists();

                        if ($pendingPayment) {
                            return 'info';
                        }

                        return 'gray';
                    })
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->width('120px'),
                Tables\Columns\TextColumn::make('discount_availed')
                    ->label('Discount')
                    ->badge()
                    ->getStateUsing(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_label'])
                    ->color(fn (Reservation $record) => self::resolveBillingSnapshot($record)['discount_applied'] ? 'success' : 'gray')
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
            ->modifyQueryUsing(fn ($query) => $query->with(['roomAssignments.room.roomType', 'preferredRoomType', 'charges', 'payments', 'billingGuest']))
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
                    ->indicateUsing(fn (array $data): ?string => ($data['enabled'] ?? false) ? 'Near Due (checkout ≤ 24h)' : null),
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
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Approve action
                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->modalHeading('Approve Reservation')
                        ->modalDescription('Approve this reservation. You may optionally assign specific rooms now to hold them for the guest\'s arrival.')
                        ->modalWidth('4xl')
                        ->visible(fn (Reservation $record) => $record->status === 'pending')
                        ->form([
                            Forms\Components\Section::make('Approval Details')
                                ->schema([
                                    Forms\Components\Textarea::make('admin_notes')
                                        ->label('Notes (optional)')
                                        ->rows(2),
                                ]),

                            Forms\Components\Section::make('Room Assignment (Optional)')
                                ->description('Assigning rooms now will hold them exclusively for this reservation. If you skip this, rooms can be assigned later during check-in.')
                                ->schema([
                                    Forms\Components\Placeholder::make('preferred_room_info')
                                        ->label('Guest\'s Room Preference')
                                        ->content(function (Reservation $record) {
                                            $preferredRoom = $record->preferred_room;
                                            if (! $preferredRoom) {
                                                return '—';
                                            }

                                            $checkIn = $record->check_in_date ? Carbon::parse($record->check_in_date) : null;
                                            $checkOut = $record->check_out_date ? Carbon::parse($record->check_out_date) : null;

                                            $isAvailable = false;
                                            if ($checkIn && $checkOut) {
                                                $isAvailable = app(RoomHoldService::class)->isRoomAvailable(
                                                    $preferredRoom,
                                                    $checkIn,
                                                    $checkOut
                                                );
                                            }

                                            $statusIcon = $isAvailable ? '✅' : '⚠️';
                                            $statusText = $isAvailable ? 'Currently Available' : 'No Longer Available';
                                            $statusColor = $isAvailable ? 'success' : 'warning';

                                            return new \Illuminate\Support\HtmlString(
                                                "<div style='display:flex;align-items:center;gap:8px;'>
                                                    <strong>{$preferredRoom->room_number}</strong>
                                                    <span style='color:".($isAvailable ? '#059669' : '#d97706').";font-weight:600;'>
                                                        {$statusIcon} {$statusText}
                                                    </span>
                                                </div>
                                                <div style='color:#6b7280;font-size:0.875rem;margin-top:4px;'>
                                                    {$preferredRoom->roomType->name}
                                                </div>"
                                            );
                                        })
                                        ->visible(fn (Reservation $record) => $record->preferred_room !== null),
                                    Forms\Components\Select::make('assigned_room_ids')
                                        ->label('Select Rooms')
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->default(function (Reservation $record) {
                                            // Auto-select preferred room if it's available
                                            $preferredRoom = $record->preferred_room;
                                            if (! $preferredRoom) {
                                                return [];
                                            }

                                            $checkIn = $record->check_in_date ? Carbon::parse($record->check_in_date) : null;
                                            $checkOut = $record->check_out_date ? Carbon::parse($record->check_out_date) : null;

                                            if (! $checkIn || ! $checkOut) {
                                                return [];
                                            }

                                            $isAvailable = app(RoomHoldService::class)->isRoomAvailable(
                                                $preferredRoom,
                                                $checkIn,
                                                $checkOut
                                            );

                                            return $isAvailable ? [$preferredRoom->id] : [];
                                        })
                                        ->options(function (callable $get, Reservation $record) {
                                            $checkIn = $record->check_in_date ? Carbon::parse($record->check_in_date) : null;
                                            $checkOut = $record->check_out_date ? Carbon::parse($record->check_out_date) : null;

                                            if (! $checkIn || ! $checkOut) {
                                                return [];
                                            }

                                            $roomType = $record->preferredRoomType;
                                            if (! $roomType) {
                                                return [];
                                            }

                                            $availableRooms = app(RoomHoldService::class)
                                                ->getAvailableRooms($roomType, $checkIn, $checkOut);

                                            if ($availableRooms->isEmpty()) {
                                                return ['' => '(No available rooms for this date range)'];
                                            }

                                            return $availableRooms->pluck('room_number', 'id')->toArray();
                                        })
                                        ->helperText(fn (Reservation $record) => $record->check_in_date && $record->check_out_date
                                            ? 'Showing rooms available from '.$record->check_in_date->format('M d, Y').' to '.$record->check_out_date->format('M d, Y')
                                            : 'Check-in and check-out dates are required to filter available rooms.'
                                        ),
                                ])->collapsible(),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->update([
                                'status' => 'approved',
                                'approved_at' => now(),
                                'admin_notes' => $data['admin_notes'] ?? $record->admin_notes,
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);

                            // Create room holds if rooms were assigned
                            $roomIds = $data['assigned_room_ids'] ?? [];
                            $preferredRoomId = $record->preferred_room_id;

                            if (! empty($roomIds)) {
                                try {
                                    $result = app(RoomHoldService::class)->createAdvanceHolds($record, $roomIds);

                                    // Check if preferred room was honored
                                    $roomNumbers = collect($roomIds)->map(fn ($id) => Room::find($id)?->room_number)->filter()->toArray();
                                    $preferredRoomAssigned = $preferredRoomId && in_array($preferredRoomId, $roomIds);

                                    $logMessage = "Approved with {$result['room_count']} room(s) held: ".implode(', ', $roomNumbers).'.';
                                    if ($preferredRoomId) {
                                        $logMessage .= $preferredRoomAssigned
                                            ? ' Guest\'s preferred room was assigned.'
                                            : ' Guest\'s preferred room was NOT assigned (unavailable or not selected).';
                                    }

                                    ReservationLog::record(
                                        $record,
                                        'room_holds_created',
                                        $logMessage,
                                        ['room_ids' => $roomIds, 'preferred_room_honored' => $preferredRoomAssigned]
                                    );

                                    $notificationTitle = 'Reservation approved with '.$result['room_count'].' room(s) held.';
                                    if ($preferredRoomId && $preferredRoomAssigned) {
                                        $notificationTitle .= ' ✓ Guest\'s preferred room assigned.';
                                    }

                                    Notification::make()
                                        ->title($notificationTitle)
                                        ->success()
                                        ->send();
                                } catch (\RuntimeException $e) {
                                    Notification::make()
                                        ->title('Reservation approved, but room holds failed: '.$e->getMessage())
                                        ->warning()
                                        ->send();
                                }
                            } else {
                                ReservationLog::record(
                                    $record,
                                    'approved_without_rooms',
                                    'Approved without room assignment. Rooms will be assigned during check-in.'
                                );
                            }
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
                            // Release any room holds before declining
                            app(RoomHoldService::class)->releaseAllHolds($record);

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
                        ->visible(fn (Reservation $record) => in_array($record->status, ['approved', 'confirmed']))
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
                                            'Male' => 'Male',
                                            'Female' => 'Female',
                                        ])
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function ($set) {
                                            $set('room_id', null);
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
                                    Forms\Components\TextInput::make('guest_age')
                                        ->label('Age')
                                        ->default(fn (Reservation $record) => $record->guest_age)
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(120),
                                ])->columns(3),

                            Forms\Components\Section::make('Room Entries')
                                ->description(fn (Reservation $record) => $record->preferredRoomType->isPrivate()
                                    ? 'Add one row per room involved in this check-in. Private reservations are room-level only (no bed assignment).'
                                    : 'Add one row per room involved in this check-in. Use PRIVATE for whole-room assignment or DORM for per-bed assignment.')
                                ->schema([
                                    Forms\Components\Repeater::make('reservation_rooms')
                                        ->default(function (Reservation $record) {
                                            // Pre-populate from advance room holds if they exist
                                            $holds = $record->roomHolds()
                                                ->advance()
                                                ->with('room.roomType')
                                                ->get();

                                            if ($holds->isEmpty()) {
                                                return [];
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
                                        ->helperText(function (Reservation $record) {
                                            $status = session('room_hold_load_status');
                                            if (! $status) {
                                                $totalHolds = $record->roomHolds()->advance()->count();
                                                if ($totalHolds > 0) {
                                                    return "✓ {$totalHolds} room(s) held from approval stage. Pre-populated above.";
                                                }

                                                return 'Add one or more rooms to proceed with check-in.';
                                            }

                                            $loaded = $status['loaded'];
                                            $skipped = $status['skipped'];
                                            $total = $status['total'];

                                            if ($skipped > 0) {
                                                return "⚠️ {$loaded}/{$total} held rooms loaded. {$skipped} skipped (inactive/unavailable).";
                                            }

                                            return "✓ {$loaded} room(s) from approval stage loaded successfully.";
                                        })
                                        ->schema([
                                            Forms\Components\Select::make('room_mode')
                                                ->label('Room Mode')
                                                ->required()
                                                ->options([
                                                    'private' => 'Private (occupies whole room)',
                                                    'dorm' => 'Dorm (per-bed assignment)',
                                                ])
                                                ->default(fn (Reservation $record) => $record->preferredRoomType?->isPrivate() ? 'private' : 'dorm'
                                                )
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

                                                    $preferredTypeId = $record->preferred_room_type_id;

                                                    // Get rooms already held for this reservation
                                                    $heldRooms = $record->roomHolds()
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
                                                        $options['🔒 Held for this Reservation'] = $heldRooms->mapWithKeys(function ($room) {
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
                                                        return ['' => '(No rooms available - select mode first)'];
                                                    }

                                                    return $options;
                                                })
                                                ->helperText(fn ($get) => filled($get('room_mode') ?? null)
                                                    ? 'Held rooms shown first. Preferred room type shown in other available rooms.'
                                                    : 'Room mode is pre-filled based on held room'),
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
                                                                '../../reservation_rooms.'.$key.'.includes_primary_guest',
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
                                                        ->native(false)
                                                        ->live(),
                                                ])
                                                ->columns(5)
                                                ->minItems(0)
                                                ->defaultItems(0)
                                                ->addActionLabel('➕ Add Another Guest')
                                                ->helperText(function ($get) {
                                                    $roomId = $get('room_id');
                                                    $mode = $get('room_mode');

                                                    $baseText = 'Add companion guests only. Primary guest is auto-included when enabled above.';

                                                    if (! $roomId || $mode !== 'dorm') {
                                                        return $baseText;
                                                    }

                                                    $room = \App\Models\Room::find($roomId);
                                                    if (! $room) {
                                                        return $baseText;
                                                    }

                                                    $occupied = $room->roomAssignments()
                                                        ->where('status', 'checked_in')
                                                        ->count();
                                                    $available = max(0, $room->capacity - $occupied);
                                                    $includesPrimary = $get('includes_primary_guest') ? 1 : 0;
                                                    $availableForCompanions = max(0, $available - $includesPrimary);

                                                    return "Room capacity: {$room->capacity} | Currently occupied: {$occupied} | Available slots: {$available} | Space for companions: {$availableForCompanions}";
                                                })
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
                                        ->default(function (Reservation $record) {
                                            $map = [
                                                'academic' => 'Academic',
                                                'official' => 'Official Business',
                                                'personal' => 'Personal',
                                                'event' => 'Event/Conference',
                                                'training' => 'Training',
                                                'research' => 'Research',
                                                'other' => 'Other',
                                            ];

                                            return $map[$record->purpose]
                                                ?? ucwords(str_replace('_', ' ', $record->purpose ?? 'Personal'));
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
                                        ->default(fn (Reservation $record) => $record->check_in_date->toDateString())
                                        ->required()
                                        ->native(false),
                                    Forms\Components\DatePicker::make('detailed_checkout_datetime')
                                        ->label('Scheduled Check-out Date')
                                        ->default(fn (Reservation $record) => $record->check_out_date->toDateString())
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
                                        ->content(fn (Reservation $record) => $record->number_of_occupants.' guest'.($record->number_of_occupants > 1 ? 's' : '')),
                                    Forms\Components\Placeholder::make('declared_days')
                                        ->label('Declared Number of Nights')
                                        ->content(function ($get, Reservation $record) {
                                            $checkIn = $get('detailed_checkin_datetime');
                                            $checkOut = $get('detailed_checkout_datetime');

                                            if ($checkIn && $checkOut) {
                                                $d = max(1, \Carbon\Carbon::parse($checkIn)->startOfDay()->diffInDays(\Carbon\Carbon::parse($checkOut)->startOfDay()));
                                            } else {
                                                $d = max(1, $record->check_in_date->startOfDay()->diffInDays($record->check_out_date->startOfDay()));
                                            }

                                            return $d.' night'.($d > 1 ? 's' : '');
                                        }),
                                    Forms\Components\Placeholder::make('live_checkin_pricing_breakdown')
                                        ->label('Estimated Payable Amount (Actual Check-in Data)')
                                        ->content(function ($get, Reservation $record) {
                                            $pricing = self::computeCheckInPricing(
                                                $record,
                                                $get('reservation_rooms') ?? [],
                                                $get('detailed_checkin_datetime'),
                                                $get('detailed_checkout_datetime'),
                                                $get('additional_requests') ?? [],
                                                // ^^ now [{code,qty}] format
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
                                            $html .= '<div><strong>Room Subtotal:</strong> ₱'.number_format($pricing['room_subtotal'], 2).'</div>';
                                            $html .= '<div><strong>Add-Ons:</strong> ₱'.number_format($pricing['services_total'], 2).'</div>';
                                            $html .= '<div><strong>Subtotal:</strong> ₱'.number_format($pricing['subtotal'], 2).'</div>';

                                            if ($pricing['discount_amount'] > 0) {
                                                $html .= '<div class="text-green-600"><strong>Discount ('.$pricing['discount_percent'].'%):</strong> -₱'.number_format($pricing['discount_amount'], 2).'</div>';
                                            }

                                            $html .= '<div class="font-semibold"><strong>Estimated Payable:</strong> ₱'.number_format($pricing['grand_total'], 2).'</div>';

                                            // Show deposit deduction if any posted payments exist
                                            $existingPayments = (float) $record->payments()
                                                ->where('status', 'posted')
                                                ->sum('amount');

                                            if ($existingPayments > 0) {
                                                $remaining = max(0, $pricing['grand_total'] - $existingPayments);
                                                $html .= '<div class="text-blue-600 mt-1"><strong>Less: Prior Payment(s):</strong> -₱'.number_format($existingPayments, 2).'</div>';
                                                $html .= '<div class="font-bold text-lg mt-1"><strong>Remaining Balance:</strong> ₱'.number_format($remaining, 2).'</div>';
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

                            Forms\Components\Section::make('Hold Duration')
                                ->schema([
                                    Forms\Components\Select::make('hold_duration_minutes')
                                        ->label('Payment Hold Duration')
                                        ->options([
                                            30 => '30 minutes',
                                            45 => '45 minutes',
                                            60 => '1 hour',
                                            90 => '1.5 hours',
                                            120 => '2 hours',
                                            240 => '4 hours',
                                            480 => '8 hours',
                                            720 => '12 hours',
                                            1440 => '24 hours',
                                        ])
                                        ->default(30)
                                        ->required()
                                        ->helperText('How long the room(s) will be held while guest walks to cashier and returns with OR.'),
                                ])->columns(1),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            try {
                                $result = app(CheckInService::class)->preparePendingPayment($record, $data);

                                Notification::make()
                                    ->success()
                                    ->title('Check-in Prepared')
                                    ->body('Hold created for '.$result['held_guest_count'].' guest(s). Expires at '.optional($result['hold_expires_at'])?->format('M d, Y h:i A').'.')
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
                            Forms\Components\Placeholder::make('payment_status_notice')
                                ->label('')
                                ->content(function (Reservation $record) {
                                    // Calculate remaining balance after prior payments
                                    $payable = self::computeHoldPayableAmount($record);
                                    $priorPayments = (float) $record->payments()
                                        ->where('status', 'posted')
                                        ->sum('amount');
                                    
                                    // Check if there was an online payment
                                    $onlinePayment = $record->payments()
                                        ->where('gateway', 'paymongo')
                                        ->where('gateway_status', 'paid')
                                        ->where('status', 'posted')
                                        ->first();

                                    if ($onlinePayment && $payable <= 0.01) {
                                        // Fully paid with no additional balance
                                        return new HtmlString(
                                            '<div class="bg-green-50 border-2 border-green-500 rounded-lg p-4">' .
                                            '<div class="flex items-center">' .
                                            '<svg class="w-6 h-6 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">' .
                                            '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>' .
                                            '</svg>' .
                                            '<div>' .
                                            '<p class="font-bold text-green-900">Fully Paid Online</p>' .
                                            '<p class="text-sm text-green-800">Guest paid ₱' . number_format($onlinePayment->amount, 2) . ' online. No additional payment needed.</p>' .
                                            '</div>' .
                                            '</div>' .
                                            '</div>'
                                        );
                                    } elseif ($onlinePayment && $payable > 0.01) {
                                        // Had online payment but there's now a balance due (add-ons added)
                                        return new HtmlString(
                                            '<div class="bg-amber-50 border-2 border-amber-500 rounded-lg p-4">' .
                                            '<div class="flex items-center">' .
                                            '<svg class="w-6 h-6 text-amber-600 mr-2" fill="currentColor" viewBox="0 0 20 20">' .
                                            '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>' .
                                            '</svg>' .
                                            '<div>' .
                                            '<p class="font-bold text-amber-900">Balance Due After Online Payment</p>' .
                                            '<p class="text-sm text-amber-800">Guest paid ₱' . number_format($priorPayments, 2) . ' online, but add-ons/charges were added. Balance of ₱' . number_format($payable, 2) . ' must be collected.</p>' .
                                            '</div>' .
                                            '</div>' .
                                            '</div>'
                                        );
                                    }

                                    return null;
                                })
                                ->visible(fn (Reservation $record) => $record->payments()->where('gateway', 'paymongo')->where('status', 'posted')->exists())
                                ->columnSpanFull(),
                            Forms\Components\Placeholder::make('hold_expiry_notice')
                                ->label('Hold Status')
                                ->content(fn (Reservation $record) => $record->checkin_hold_expires_at
                                    ? 'Hold expires on '.$record->checkin_hold_expires_at->format('M d, Y h:i A')
                                    : 'No hold expiry recorded.'),
                            Forms\Components\Placeholder::make('payable_amount_notice')
                                ->label('Payable Amount')
                                ->content(function (Reservation $record) {
                                    $payable = self::computeHoldPayableAmount($record);

                                    // Check if there are existing posted payments (e.g. online deposit)
                                    $existingPayments = (float) $record->payments()
                                        ->where('status', 'posted')
                                        ->sum('amount');

                                    if ($existingPayments > 0) {
                                        $grossTotal = $payable + $existingPayments;
                                        $html = '<div class="text-sm space-y-1">';
                                        $html .= '<div>Total Charges: ₱'.number_format($grossTotal, 2).'</div>';
                                        $html .= '<div class="text-blue-600">Less: Prior Payment(s): -₱'.number_format($existingPayments, 2).'</div>';
                                        $html .= '<div class="font-bold text-lg">Remaining Balance: ₱'.number_format($payable, 2).'</div>';
                                        $html .= '</div>';

                                        return new HtmlString($html);
                                    }

                                    return $payable > 0
                                        ? '₱'.number_format($payable, 2)
                                        : 'No payable amount available. Re-prepare check-in if needed.';
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
                                ->disabled(fn (Reservation $record) => self::computeHoldPayableAmount($record) <= 0.01)
                                ->required(fn (Reservation $record) => self::computeHoldPayableAmount($record) > 0.01),
                            Forms\Components\TextInput::make('payment_mode_other')
                                ->label('Specify Payment Mode')
                                ->visible(fn ($get) => $get('payment_mode') === 'others')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('payment_amount')
                                ->label('Paid Amount')
                                ->numeric()
                                ->prefix('₱')
                                ->default(function (Reservation $record) {
                                    // Return computed payable amount (accounts for prior payments)
                                    return self::computeHoldPayableAmount($record);
                                })
                                ->disabled(fn (Reservation $record) => self::computeHoldPayableAmount($record) <= 0.01)
                                ->helperText(fn (Reservation $record) => self::computeHoldPayableAmount($record) <= 0.01
                                    ? 'Already fully paid online - no additional payment required'
                                    : null
                                )
                                ->required(fn (Reservation $record) => self::computeHoldPayableAmount($record) > 0.01),
                            Forms\Components\TextInput::make('payment_or_number')
                                ->label('Official Receipt Number')
                                ->required(fn (Reservation $record) => self::computeHoldPayableAmount($record) > 0.01)
                                ->disabled(fn (Reservation $record) => self::computeHoldPayableAmount($record) <= 0.01)
                                ->maxLength(100),
                            Forms\Components\DatePicker::make('or_date')
                                ->label('OR Date')
                                ->displayFormat('M d, Y')
                                ->default(now()->toDateString())
                                ->required(fn (Reservation $record) => self::computeHoldPayableAmount($record) > 0.01)
                                ->disabled(fn (Reservation $record) => self::computeHoldPayableAmount($record) <= 0.01)
                                ->helperText('Date on the official receipt'),
                            Forms\Components\Textarea::make('remarks')
                                ->label('Final Check-in Remarks')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            try {
                                $payable = self::computeHoldPayableAmount($record);
                                $paidAmount = (float) ($data['payment_amount'] ?? 0);

                                if ($payable > 0 && $paidAmount + 0.00001 < $payable) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Paid Amount Too Low')
                                        ->body('Paid amount cannot be less than payable amount of ₱'.number_format($payable, 2).'.')
                                        ->persistent()
                                        ->send();

                                    return;
                                }

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

                    Tables\Actions\Action::make('extend_hold')
                        ->label('Extend Hold')
                        ->icon('heroicon-o-clock')
                        ->color('info')
                        ->visible(fn (Reservation $record) => $record->status === 'pending_payment')
                        ->form([
                            Forms\Components\Placeholder::make('current_expiry')
                                ->label('Current Hold Expiry')
                                ->content(fn (Reservation $record) => $record->checkin_hold_expires_at
                                    ? $record->checkin_hold_expires_at->format('M d, Y h:i A')
                                    : 'No active hold'),
                            Forms\Components\Select::make('additional_minutes')
                                ->label('Extend By')
                                ->required()
                                ->default(15)
                                ->options([
                                    15 => '15 minutes',
                                    30 => '30 minutes',
                                    45 => '45 minutes',
                                    60 => '1 hour',
                                ])
                                ->helperText('How many additional minutes to add to the current hold.'),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            try {
                                $result = app(CheckInService::class)->extendPendingPaymentHold(
                                    $record,
                                    $data['additional_minutes'] ?? 15
                                );

                                Notification::make()
                                    ->success()
                                    ->title('Hold Extended')
                                    ->body('Hold extended by '.$result['minutes_added'].' minute(s). New expiry: '.$result['new_expiry']->format('M d, Y h:i A').'.')
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Unable to Extend Hold')
                                    ->body($e->getMessage())
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
                            Forms\Components\DatePicker::make('checked_out_at')
                                ->label('Check-out Date')
                                ->default(fn () => now())
                                ->required()
                                ->native(false),
                            Forms\Components\Textarea::make('remarks')
                                ->label('Check-out Remarks')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $checkoutAt = $data['checked_out_at'] ?? now();
                            // Close ALL room assignments without a checkout timestamp.
                            // Use individual model saves so RoomAssignmentObserver fires
                            // and each room's status is recalculated immediately.
                            RoomAssignment::where('reservation_id', $record->id)
                                ->whereNull('checked_out_at')
                                ->get()
                                ->each(fn ($assignment) => $assignment->update([
                                    'status' => 'checked_out',
                                    'checked_out_at' => $checkoutAt,
                                    'checked_out_by' => auth()->id(),
                                ]));

                            // Add remarks if provided to all assignments
                            if ($data['remarks']) {
                                RoomAssignment::where('reservation_id', $record->id)
                                    ->each(function ($assignment) use ($data) {
                                        $assignment->update([
                                            'remarks' => $assignment->remarks
                                                ? $assignment->remarks.' | '.$data['remarks']
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
                        ->visible(fn (Reservation $record) => in_array($record->status, ['pending', 'approved', 'confirmed', 'pending_payment']))
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

                            // Release any room holds before cancelling
                            app(RoomHoldService::class)->releaseAllHolds($record);

                            $record->update([
                                'status' => 'cancelled',
                                'admin_notes' => $data['admin_notes'],
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
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
                    Tables\Actions\Action::make('copy_payment_link')
                        ->label('Copy Payment Link')
                        ->icon('heroicon-o-clipboard-document')
                        ->color('info')
                        ->visible(fn (Reservation $record) => Setting::isOnlinePaymentsEnabled() &&
                            $record->isPaymentLinkValid() &&
                            in_array($record->status, ['approved', 'confirmed'])
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
                                            .'<img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode($record->generatePaymentLink()).'" '
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
                                            .'<li><strong>Print QR code:</strong> Right-click the QR code image → "Save image as...", then print it to give to the guest.</li>'
                                            .'</ol>'
                                            .'<p style="color:#059669;"><strong>✓ Tip:</strong> The QR code works great for walk-in guests or phone-based sharing!</p>'
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

                    // ── Bulk Approve (admin+) ─────────────────────────
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn () => auth()->user()->isAdmin())
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
                                $record->update([
                                    'status' => 'approved',
                                    'approved_at' => now(),
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                ]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} reservation(s) approved")
                                ->success()
                                ->send();
                        }),

                    // ── Bulk Decline (admin+) ─────────────────────────
                    Tables\Actions\BulkAction::make('bulk_decline')
                        ->label('Decline selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn () => auth()->user()->isAdmin())
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
                                $record->update([
                                    'status' => 'declined',
                                    'admin_notes' => $data['admin_notes'] ?? null,
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                ]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} reservation(s) declined")
                                ->success()
                                ->send();
                        }),

                    // ── Bulk Cancel (admin+) ──────────────────────────
                    Tables\Actions\BulkAction::make('bulk_cancel')
                        ->label('Cancel selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('warning')
                        ->visible(fn () => auth()->user()->isAdmin())
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
                                $record->update([
                                    'status' => 'cancelled',
                                    'admin_notes' => $data['admin_notes'] ?? null,
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                ]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} reservation(s) cancelled")
                                ->success()
                                ->send();
                        }),

                    // ── Bulk Delete (super_admin only + password) ─────
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
            ->deferLoading()
            ->extremePaginationLinks();
    }

    public static function getRelations(): array
    {
        return [
            ReservationResource\RelationManagers\RoomAssignmentsRelationManager::class,
            ReservationResource\RelationManagers\PaymentsRelationManager::class,
            ReservationResource\RelationManagers\StayLogsRelationManager::class,
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

    protected static function computeCheckInPricing(
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

    protected static function computeHoldPayableAmount(Reservation $record): float
    {
        $holdPayload = $record->checkin_hold_payload ?? [];
        $holdEntries = data_get($holdPayload, 'entries', []);
        $holdData = data_get($holdPayload, 'payload', []);

        if (! is_array($holdEntries) || empty($holdEntries)) {
            return (float) data_get($holdData, 'payment_amount', 0);
        }

        $pricing = self::computeCheckInPricing(
            $record,
            $holdEntries,
            null,
            null,
            data_get($holdData, 'additional_requests', []),
            (bool) data_get($holdData, 'is_pwd', false),
            (bool) data_get($holdData, 'is_senior_citizen', false),
            (bool) data_get($holdData, 'is_student', false)
        );

        $grossTotal = round((float) ($pricing['grand_total'] ?? 0), 2);

        // Subtract existing posted payments (e.g. online deposits) from payable
        $existingPayments = (float) $record->payments()
            ->where('status', 'posted')
            ->sum('amount');

        return round(max(0, $grossTotal - $existingPayments), 2);
    }

    /**
     * @return array{guest_name:string,payment_mode:string,payment_amount:float,or_number:?string,or_date:mixed,addons_label:string,discount_applied:bool,discount_label:string,discount_amount:float}
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

        $holdPayload = data_get($record->checkin_hold_payload, 'payload', []);

        $paymentModeRaw = $latestPayment?->payment_mode
            ?? $paidAssignment?->payment_mode
            ?? (string) data_get($holdPayload, 'payment_mode', '');
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

                return $legacy ? [ucfirst((string) $legacy)] : [];
            })
            ->filter()
            ->values();

        $additionalRequests = $paidAssignment?->additional_requests
            ?? data_get($holdPayload, 'additional_requests', []);

        $billingGuestName = $record->billingGuest
            ? trim(($record->billingGuest->first_name ?? '').' '.($record->billingGuest->last_name ?? ''))
            : '';
        if ($billingGuestName === '' && $record->billingGuest?->full_name) {
            $billingGuestName = (string) $record->billingGuest->full_name;
        }

        $paymentAmount = (float) ($latestPayment?->amount
            ?? $paidAssignment?->payment_amount
            ?? (float) data_get($holdPayload, 'payment_amount', 0));
        if ($paymentAmount <= 0 && (float) ($record->payments_total ?? 0) > 0) {
            $paymentAmount = (float) $record->payments_total;
        }

        $holdIsPwd = (bool) data_get($holdPayload, 'is_pwd', false);
        $holdIsSenior = (bool) data_get($holdPayload, 'is_senior_citizen', false);
        $holdIsStudent = (bool) data_get($holdPayload, 'is_student', false);

        $holdDiscountParts = [];
        if ($holdIsPwd) {
            $holdDiscountParts[] = 'PWD';
        }
        if ($holdIsSenior) {
            $holdDiscountParts[] = 'Senior Citizen';
        }
        if ($holdIsStudent) {
            $holdDiscountParts[] = 'Student';
        }

        $discountLabel = '-';
        if ($discountTypes->isNotEmpty()) {
            $discountLabel = $discountTypes->implode(', ');
        } elseif (! empty($holdDiscountParts)) {
            $discountLabel = implode(', ', $holdDiscountParts);
        }

        $discountApplied = $discountTotal > 0 || ! empty($holdDiscountParts);

        return [
            'guest_name' => $billingGuestName !== ''
                ? $billingGuestName
                : ($paidAssignment
                    ? trim(($paidAssignment->guest_first_name ?? '').' '.($paidAssignment->guest_last_name ?? ''))
                    : (string) $record->guest_name),
            'payment_mode' => $paymentMode,
            'payment_amount' => $paymentAmount,
            'or_number' => $latestPayment?->reference_no
                ?? $paidAssignment?->payment_or_number
                ?? data_get($holdPayload, 'payment_or_number'),
            'or_date' => $latestPayment?->or_date
                ?? $paidAssignment?->or_date
                ?? data_get($holdPayload, 'or_date'),
            'discount_applied' => $discountApplied,
            'discount_label' => $discountApplied ? $discountLabel : 'No',
            'discount_amount' => $discountTotal,
            'addons_label' => $addonsFromLedger->isNotEmpty()
                ? $addonsFromLedger->implode(', ')
                : self::formatServiceCodes(is_array($additionalRequests) ? $additionalRequests : []),
        ];
    }

    /**
     * @return array{id_type:?string,id_number:?string,nationality:?string,purpose:?string,arrival_at:?string,scheduled_checkout_at:?string,remarks:?string}
     */
    protected static function resolveCheckInSnapshot(Reservation $record): array
    {
        $holdPayload = data_get($record->checkin_hold_payload, 'payload', []);
        $assignment = $record->roomAssignments()->latest('checked_in_at')->first();
        $snapshot = $record->checkInSnapshots()->latest('id')->first();
        // Date & Time of Arrival = staff-entered detailed_checkin_datetime
        $arrivalAtRaw = $snapshot?->detailed_checkin_datetime
            ?? $assignment?->detailed_checkin_datetime
            ?? data_get($holdPayload, 'detailed_checkin_datetime');
        // Scheduled Check-out = staff-entered detailed_checkout_datetime
        $scheduledCheckoutRaw = $snapshot?->detailed_checkout_datetime
            ?? $assignment?->detailed_checkout_datetime
            ?? data_get($holdPayload, 'detailed_checkout_datetime');

        return [
            'id_type' => $snapshot?->id_type
                ?? $assignment?->id_type
                ?? data_get($holdPayload, 'id_type'),
            'id_number' => $snapshot?->id_number
                ?? $assignment?->id_number
                ?? data_get($holdPayload, 'id_number'),
            'nationality' => $snapshot?->nationality
                ?? $assignment?->nationality
                ?? data_get($holdPayload, 'nationality', 'Filipino'),
            'purpose' => $snapshot?->purpose_of_stay
                ?? $assignment?->purpose_of_stay
                ?? data_get($holdPayload, 'purpose_of_stay')
                ?? ucwords(str_replace('_', ' ', (string) $record->purpose)),
            'checkin_at' => $arrivalAtRaw
                ? Carbon::parse($arrivalAtRaw)->format('M d, Y')
                : null,
            'checkout_at' => $scheduledCheckoutRaw
                ? Carbon::parse($scheduledCheckoutRaw)->format('M d, Y')
                : null,
            'remarks' => $snapshot?->remarks
                ?? $assignment?->remarks
                ?? data_get($holdPayload, 'remarks'),
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
                $linePrice = $qty > 1 ? '₱'.number_format((float) $service->price * $qty, 2) : null;
                $label = $service->name.($service->price > 0
                    ? ' ('.($linePrice ?? '₱'.number_format((float) $service->price, 2)).')'
                    : ' (Free)');

                return $qty > 1 ? "{$qty}x {$label}" : $label;
            })->filter()->values();
        } else {
            $names = Service::whereIn('code', array_filter($serviceCodes))
                ->get()
                ->map(fn (Service $service) => $service->name.($service->price > 0 ? ' (₱'.number_format((float) $service->price, 2).')' : ' (Free)')
                )
                ->values();
        }

        return $names->isEmpty() ? 'No add-ons selected' : $names->implode(', ');
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
