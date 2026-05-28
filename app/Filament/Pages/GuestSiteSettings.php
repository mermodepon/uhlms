<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\NotificationHelper;
use App\Support\GuestSiteSettings as GuestSiteSettingsSupport;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GuestSiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 22;

    protected static ?string $title = 'Guest Site Settings';

    protected static ?string $navigationLabel = 'Guest Site Settings';

    protected static string $view = 'filament.pages.guest-site-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->hasPermission('guest_site_settings_view') || $user?->hasPermission('guest_site_settings_edit')) ?? false;
    }

    public static function canEdit(): bool
    {
        return auth()->user()?->hasPermission('guest_site_settings_edit') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(GuestSiteSettingsSupport::all());
    }

    public function form(Form $form): Form
    {
        $readOnly = ! static::canEdit();

        return $form
            ->disabled($readOnly)
            ->schema([
                Forms\Components\Section::make('Branding')
                    ->description('Public identity used in the guest navigation, page title, logo, and footer.')
                    ->icon('heroicon-o-sparkles')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('guest_site_title')
                            ->label('Browser Title')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('guest_institution_name')
                            ->label('Institution Name')
                            ->required()
                            ->maxLength(160),
                        Forms\Components\TextInput::make('guest_brand_name')
                            ->label('Brand Name')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\ColorPicker::make('guest_primary_accent_color')
                            ->label('Accent Color')
                            ->required(),
                        Forms\Components\Select::make('guest_theme_font')
                            ->label('Font Mode')
                            ->options([
                                'sans' => 'Sans',
                                'serif' => 'Serif',
                                'mono' => 'Mono',
                            ])
                            ->required(),
                        Forms\Components\FileUpload::make('guest_logo')
                            ->label('Logo / Favicon')
                            ->image()
                            ->disk(config('media.disk', 'public'))
                            ->directory('site-settings')
                            ->visibility('public')
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Public Notices')
                    ->description('Short banners shown at the top of guest pages.')
                    ->icon('heroicon-o-megaphone')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('guest_announcement_enabled')
                            ->label('Show Announcement')
                            ->inline(false),
                        Forms\Components\Textarea::make('guest_announcement_text')
                            ->label('Announcement Text')
                            ->rows(2)
                            ->maxLength(500),
                        Forms\Components\Toggle::make('guest_maintenance_enabled')
                            ->label('Show Maintenance Banner')
                            ->inline(false),
                        Forms\Components\Textarea::make('guest_maintenance_message')
                            ->label('Maintenance Message')
                            ->rows(2)
                            ->maxLength(500),
                    ]),

                Forms\Components\Section::make('Homepage Hero')
                    ->description('Main headline, welcome copy, CTAs, and quick availability controls.')
                    ->icon('heroicon-o-home')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('guest_hero_badge')
                            ->label('Badge Text')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('guest_hero_headline')
                            ->label('Headline')
                            ->required()
                            ->maxLength(140),
                        Forms\Components\Textarea::make('guest_hero_message')
                            ->label('Welcome Message')
                            ->rows(3)
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('guest_hero_background_enabled')
                            ->label('Show Hero Background Image')
                            ->helperText('Keeps the current green gradient when disabled or when no image is uploaded.')
                            ->inline(false),
                        Forms\Components\TextInput::make('guest_hero_background_opacity')
                            ->label('Dark Overlay Strength')
                            ->helperText('Use 45-65% for banner graphics, or 70-85% for bright photos.')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(45)
                            ->maxValue(85)
                            ->default(80)
                            ->required(),
                        Forms\Components\FileUpload::make('guest_hero_background_image')
                            ->label('Hero Background Image')
                            ->helperText('Use a wide landscape photo of the homestay, rooms, or common areas.')
                            ->image()
                            ->disk(config('media.disk', 'public'))
                            ->directory('site-settings/hero')
                            ->visibility('public')
                            ->imageEditor()
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('guest_hero_bullets')
                            ->label('Hero Bullets')
                            ->simple(
                                Forms\Components\TextInput::make('text')
                                    ->required()
                                    ->maxLength(160)
                            )
                            ->minItems(0)
                            ->maxItems(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('guest_show_virtual_tour_cta')
                            ->label('Show Virtual Tour CTA')
                            ->inline(false),
                        Forms\Components\Toggle::make('guest_show_quick_availability')
                            ->label('Show Quick Availability Widget')
                            ->inline(false),
                        Forms\Components\TextInput::make('guest_hero_primary_cta_label')
                            ->label('Primary CTA Label')
                            ->maxLength(80),
                        Forms\Components\TextInput::make('guest_hero_secondary_cta_label')
                            ->label('Secondary CTA Label')
                            ->maxLength(80),
                    ]),

                Forms\Components\Section::make('Homepage Sections')
                    ->description('Headings and optional guest guidance content shown below the hero.')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('guest_home_rooms_heading')
                            ->label('Rooms Section Heading')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\Textarea::make('guest_home_rooms_intro')
                            ->label('Rooms Section Intro')
                            ->rows(2)
                            ->required()
                            ->maxLength(400),
                        Forms\Components\Toggle::make('guest_show_stay_guide')
                            ->label('Show Stay Guide')
                            ->inline(false),
                        Forms\Components\TextInput::make('guest_stay_guide_heading')
                            ->label('Stay Guide Heading')
                            ->maxLength(160),
                        Forms\Components\Textarea::make('guest_stay_guide_intro')
                            ->label('Stay Guide Intro')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('guest_show_booking_policy')
                            ->label('Show Booking Policy')
                            ->inline(false),
                        Forms\Components\Textarea::make('guest_booking_policy')
                            ->label('Booking Policy')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('guest_show_faq')
                            ->label('Show FAQ')
                            ->inline(false),
                        Forms\Components\Repeater::make('guest_faq_items')
                            ->label('FAQ Items')
                            ->schema([
                                Forms\Components\TextInput::make('question')
                                    ->required()
                                    ->maxLength(200),
                                Forms\Components\Textarea::make('answer')
                                    ->required()
                                    ->rows(2)
                                    ->maxLength(1000),
                            ])
                            ->columns(1)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Reservation Guide')
                    ->description('Steps shown in the public How to Reserve section.')
                    ->icon('heroicon-o-list-bullet')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('guest_reservation_steps_heading')
                            ->label('Section Heading')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('guest_reservation_steps_intro')
                            ->label('Section Intro')
                            ->required()
                            ->maxLength(240),
                        Forms\Components\Repeater::make('guest_reservation_steps')
                            ->label('Steps')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(80),
                                Forms\Components\Textarea::make('description')
                                    ->required()
                                    ->rows(2)
                                    ->maxLength(240),
                            ])
                            ->minItems(1)
                            ->maxItems(4)
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Navigation & Footer')
                    ->description('Labels, contact details, and footer text for public pages.')
                    ->icon('heroicon-o-bars-3-bottom-left')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('guest_nav_home_label')->label('Home Label')->required()->maxLength(40),
                        Forms\Components\TextInput::make('guest_nav_rooms_label')->label('Rooms Label')->required()->maxLength(40),
                        Forms\Components\TextInput::make('guest_nav_tour_label')->label('Tour Label')->required()->maxLength(40),
                        Forms\Components\TextInput::make('guest_nav_reserve_label')->label('Reserve Label')->required()->maxLength(40),
                        Forms\Components\TextInput::make('guest_nav_track_label')->label('Track Label')->required()->maxLength(40),
                        Forms\Components\Textarea::make('guest_footer_address')->label('Address')->rows(3)->columnSpanFull(),
                        Forms\Components\TextInput::make('guest_footer_phone')->label('Phone')->maxLength(120),
                        Forms\Components\TextInput::make('guest_footer_email')->label('Email')->email()->maxLength(160),
                        Forms\Components\TextInput::make('guest_footer_copyright_name')->label('Copyright Name')->required()->maxLength(180),
                        Forms\Components\TextInput::make('guest_footer_rooms_label')->label('Footer Rooms Label')->required()->maxLength(60),
                        Forms\Components\TextInput::make('guest_footer_tour_label')->label('Footer Tour Label')->required()->maxLength(60),
                        Forms\Components\TextInput::make('guest_footer_reserve_label')->label('Footer Reserve Label')->required()->maxLength(80),
                        Forms\Components\TextInput::make('guest_footer_track_label')->label('Footer Track Label')->required()->maxLength(80),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if (! static::canEdit()) {
            Notification::make()
                ->title('Access denied.')
                ->body('You do not have permission to edit guest site settings.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();
        $before = GuestSiteSettingsSupport::all();

        Setting::withoutEvents(function () use ($data) {
            foreach (array_keys(GuestSiteSettingsSupport::defaults()) as $key) {
                GuestSiteSettingsSupport::set($key, $data[$key] ?? null);
            }
        });

        if ($before !== GuestSiteSettingsSupport::all()) {
            $actor = auth()->user();
            $actorName = $actor?->name ?? 'Someone';
            $recipients = User::whereIn('role', ['admin', 'staff'])
                ->where('id', '!=', $actor?->id)
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('guest_site_settings_view'))
                ->pluck('id')
                ->toArray();

            NotificationHelper::notifyUsers(
                $recipients,
                'Guest Site Settings Updated',
                "{$actorName} updated guest-facing site settings.",
                'info',
                'setting',
                route('filament.admin.pages.guest-site-settings', [], false)
            );
        }

        Notification::make()
            ->title('Guest site settings saved successfully.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        if (! static::canEdit()) {
            return [];
        }

        return [
            Action::make('save')
                ->label('Save Guest Site Settings')
                ->submit('save')
                ->icon('heroicon-o-check')
                ->color('success'),
        ];
    }
}
