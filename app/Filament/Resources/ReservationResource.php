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
use App\Services\ReservationWorkflowService;
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
                                                ? '-PHP '.number_format((float) self::resolveBillingSnapshot($record)['discount_amount'], 2)
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
                                    ->description('Secure payment link for approved or confirmed reservations via GCash, Maya, or Card.')
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
                                            ->default(fn (Reservation $record) => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($record->generatePaymentLink())
                                            )
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
                Tables\Columns\TextColumn::make('preferredRoomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->width('130px')
                    ->getStateUsing(function (Reservation $record) {
                        // Show actual room type from assignment when checked in/out
                        if (in_array($record->status, ['checked_in', 'checked_out'], true)) {
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
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn ($state, $record): string => match (true) {
                        $state === 'pending' => 'warning',
                        $state === 'approved' && $record->roomAssignments->isEmpty() => 'info',
                        $state === 'approved' => 'primary',
                        $state === 'confirmed' => 'primary',

                        $state === 'declined' => 'danger',
                        $state === 'cancelled' => 'gray',
                        $state === 'checked_in' => 'success',
                        $state === 'checked_out' => 'gray',
                        default => 'gray',
                    }),
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
                'roomHolds.room', // For room display info
            ]))
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
                        ->slideOver(),

                    // Approve action
                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->modalHeading('Approve Reservation')
                        ->successNotificationTitle('Reservation approved')
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
                                    Forms\Components\Select::make('assigned_room_ids')
                                        ->label('Select Rooms')
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
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
                            $result = app(ReservationWorkflowService::class)->approve($record, $data);

                            if ($result['hold_error']) {
                                Notification::make()
                                    ->title('Reservation approved, but room holds failed: '.$result['hold_error'])
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
                            app(ReservationWorkflowService::class)->checkOut(
                                $record,
                                $data['checked_out_at'] ?? now(),
                                $data['remarks'] ?? null
                            );

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
                            in_array($record->status, ['approved', 'confirmed'], true)
                        )
                        ->modalHeading('Refresh payment link')
                        ->modalDescription('Generate a fresh payment link for this reservation and optionally email it to the guest.')
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
                                app(ReservationWorkflowService::class)->cancel($record, $data['admin_notes'] ?? null);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} reservation(s) cancelled")
                                ->success()
                                ->send();
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

        $billingGuestName = $record->billingGuest
            ? trim(($record->billingGuest->first_name ?? '').' '.($record->billingGuest->last_name ?? ''))
            : '';
        if ($billingGuestName === '' && $record->billingGuest?->full_name) {
            $billingGuestName = (string) $record->billingGuest->full_name;
        }

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

        return [
            'guest_name' => $billingGuestName !== ''
                ? $billingGuestName
                : ($paidAssignment
                    ? trim(($paidAssignment->guest_first_name ?? '').' '.($paidAssignment->guest_last_name ?? ''))
                    : (string) $record->guest_name),
            'payment_mode' => $paymentMode,
            'payment_amount' => $paymentAmount,
            'or_number' => $latestPayment?->reference_no
                ?? $paidAssignment?->payment_or_number,
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
