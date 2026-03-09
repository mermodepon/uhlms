<?php

namespace App\Filament\Pages;

use App\Models\Notification as NotificationModel;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Site Settings';

    protected static ?string $navigationLabel = 'Site Settings';

    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->hasPermission('settings_view') || $user?->hasPermission('settings_edit')) ?? false;
    }

    public static function canEdit(): bool
    {
        return auth()->user()?->hasPermission('settings_edit') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            // Branding
            'site_title'      => Setting::get('site_title', 'CMU University Homestay'),
            'site_tagline'    => Setting::get('site_tagline', 'Lodging Management System'),
            'site_logo'       => Setting::get('site_logo'),
            'theme_font'      => Setting::get('theme_font', 'sans'),

            // Contact & Social
            'contact_phone'     => Setting::get('contact_phone'),
            'contact_email'     => Setting::get('contact_email'),
            'contact_address'   => Setting::get('contact_address'),
            'contact_map_embed' => Setting::get('contact_map_embed'),
            'social_facebook'   => Setting::get('social_facebook'),
            'social_instagram'  => Setting::get('social_instagram'),
            'social_twitter'    => Setting::get('social_twitter'),

            // Hero & Welcome
            'hero_banner'             => Setting::get('hero_banner'),
            'hero_banner_embed'       => Setting::get('hero_banner_embed'),
            'hero_banner_embed_enabled' => (bool) Setting::get('hero_banner_embed_enabled', 0),
            'welcome_message'         => Setting::get('welcome_message'),

            // About & Amenities
            'about_text'    => Setting::get('about_text'),
            'show_amenities' => (bool) Setting::get('show_amenities', 0),
            'amenities'     => json_decode(Setting::get('amenities', '[]'), true) ?? [],

            // Booking Policy & FAQ
            'booking_policy' => Setting::get('booking_policy'),
            'faq'            => json_decode(Setting::get('faq', '[]'), true) ?? [],

            // Announcement
            'show_announcement' => (bool) Setting::get('show_announcement', 0),
            'announcement_text' => Setting::get('announcement_text'),

            // Maintenance & Accessibility
            'maintenance_mode'             => (bool) Setting::get('maintenance_mode', 0),
            'maintenance_message'          => Setting::get('maintenance_message'),
            'accessibility_high_contrast'  => (bool) Setting::get('accessibility_high_contrast', 0),
            'accessibility_large_text'     => (bool) Setting::get('accessibility_large_text', 0),
        ]);
    }

    public function form(Form $form): Form
    {
        $readOnly = ! static::canEdit();

        return $form
            ->disabled($readOnly)
            ->schema([
                Forms\Components\Section::make('Site Branding')
                    ->description('General site identity and appearance')
                    ->icon('heroicon-o-paint-brush')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('site_title')
                            ->label('Site Title')
                            ->maxLength(60)
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('site_tagline')
                            ->label('Tagline')
                            ->maxLength(120)
                            ->columnSpan(1),
                        Forms\Components\FileUpload::make('site_logo')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('images')
                            ->visibility('public')
                            ->imagePreviewHeight('80')
                            ->columnSpan(1),
                        Forms\Components\Select::make('theme_font')
                            ->label('Font')
                            ->options([
                                'sans'  => 'Sans Serif',
                                'serif' => 'Serif',
                                'mono'  => 'Monospace',
                            ])
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Contact & Social')
                    ->description('How guests can reach you')
                    ->icon('heroicon-o-phone')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Phone')
                            ->maxLength(30)
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('contact_email')
                            ->label('Email')
                            ->maxLength(60)
                            ->email()
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('contact_address')
                            ->label('Address')
                            ->rows(3)
                            ->maxLength(300)
                            ->columnSpanFull()
                            ->helperText('Plain text — line breaks will be preserved on the guest page.'),
                        Forms\Components\TextInput::make('contact_map_embed')
                            ->label('Google Maps Embed URL')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Paste the src URL from the Google Maps embed iframe.'),
                        Forms\Components\TextInput::make('social_facebook')
                            ->label('Facebook URL')
                            ->maxLength(120)
                            ->url()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('social_instagram')
                            ->label('Instagram URL')
                            ->maxLength(120)
                            ->url()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('social_twitter')
                            ->label('Twitter / X URL')
                            ->maxLength(120)
                            ->url()
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Hero & Welcome')
                    ->description('Homepage hero banner and welcome message')
                    ->icon('heroicon-o-home')
                    ->schema([
                        Forms\Components\FileUpload::make('hero_banner')
                            ->label('Hero Banner Image')
                            ->image()
                            ->disk('public')
                            ->directory('images')
                            ->visibility('public')
                            ->imagePreviewHeight('160'),
                        Forms\Components\Toggle::make('hero_banner_embed_enabled')
                            ->label('Use Embed for Hero')
                            ->helperText('Enable to render the embed URL instead of the hero image.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('hero_banner_embed')
                            ->label('Hero Banner Embed URL')
                            ->url()
                            ->placeholder('https://tour.panoee.net/...')
                            ->helperText('Paste a Panoee or other virtual-tour URL to embed in the hero. If set, this will be used instead of the static hero image.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('welcome_message')
                            ->label('Welcome Message')
                            ->rows(3)
                            ->maxLength(400),
                    ]),

                Forms\Components\Section::make('About & Amenities')
                    ->description('About section and list of amenities shown on the homepage')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Forms\Components\Textarea::make('about_text')
                            ->label('About Section Text')
                            ->rows(4)
                            ->maxLength(800),
                        Forms\Components\Toggle::make('show_amenities')
                            ->label('Show Amenities Section'),
                        Forms\Components\Repeater::make('amenities')
                            ->label('Amenities')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Amenity Name')
                                    ->maxLength(40)
                                    ->required(),
                                Forms\Components\FileUpload::make('image')
                                    ->label('Icon / Image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('images')
                                    ->visibility('public'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add Amenity')
                            ->collapsible()
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make('Booking Policy & FAQ')
                    ->description('Booking terms and frequently asked questions displayed on the homepage')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\Textarea::make('booking_policy')
                            ->label('Booking Policy & Terms')
                            ->rows(5)
                            ->maxLength(1000),
                        Forms\Components\Repeater::make('faq')
                            ->label('FAQ Items')
                            ->schema([
                                Forms\Components\TextInput::make('question')
                                    ->label('Question')
                                    ->maxLength(200)
                                    ->required(),
                                Forms\Components\Textarea::make('answer')
                                    ->label('Answer')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->required(),
                            ])
                            ->addActionLabel('Add FAQ Item')
                            ->collapsible()
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make('Announcement Bar')
                    ->description('Urgent notices, promos, or events shown at the top of every guest page')
                    ->icon('heroicon-o-megaphone')
                    ->schema([
                        Forms\Components\Toggle::make('show_announcement')
                            ->label('Show Announcement Bar'),
                        Forms\Components\Textarea::make('announcement_text')
                            ->label('Announcement Text')
                            ->rows(2)
                            ->maxLength(250),
                    ]),

                Forms\Components\Section::make('Maintenance & Accessibility')
                    ->description('Site status and accessibility options')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Enable Maintenance Mode')
                            ->helperText('Shows a warning bar to all visitors.')
                            ->columnSpan(1),
                        Forms\Components\Toggle::make('accessibility_high_contrast')
                            ->label('High Contrast Mode')
                            ->columnSpan(1),
                        Forms\Components\Toggle::make('accessibility_large_text')
                            ->label('Large Text Mode')
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('maintenance_message')
                            ->label('Maintenance Message')
                            ->rows(2)
                            ->maxLength(250)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if (! static::canEdit()) {
            Notification::make()
                ->title('Access denied.')
                ->body('You do not have permission to edit site settings.')
                ->danger()
                ->send();
            return;
        }

        $data = $this->form->getState();

        // Section labels for change detection
        $sectionKeys = [
            'Site Branding'              => ['site_title', 'site_tagline', 'site_logo', 'theme_font'],
            'Contact & Social'           => ['contact_phone', 'contact_email', 'contact_address', 'contact_map_embed', 'social_facebook', 'social_instagram', 'social_twitter'],
            'Hero & Welcome'             => ['hero_banner', 'hero_banner_embed', 'hero_banner_embed_enabled', 'welcome_message'],
            'About & Amenities'          => ['about_text', 'show_amenities', 'amenities'],
            'Booking Policy & FAQ'       => ['booking_policy', 'faq'],
            'Announcement Bar'           => ['show_announcement', 'announcement_text'],
            'Maintenance & Accessibility'=> ['maintenance_mode', 'maintenance_message', 'accessibility_high_contrast', 'accessibility_large_text'],
        ];

        $jsonFields = ['amenities', 'faq'];
        $boolFields = ['show_amenities', 'show_announcement', 'maintenance_mode', 'accessibility_high_contrast', 'accessibility_large_text', 'hero_banner_embed_enabled'];

        // Capture current values before saving so we can detect what changed
        $changedSections = [];
        foreach ($sectionKeys as $section => $keys) {
            foreach ($keys as $key) {
                $current = Setting::get($key, '');
                $new = in_array($key, $jsonFields)
                    ? json_encode($data[$key] ?? [])
                    : (in_array($key, $boolFields) ? ($data[$key] ? '1' : '0') : ($data[$key] ?? ''));

                if ((string) $current !== (string) $new) {
                    $changedSections[$section] = true;
                    break;
                }
            }
        }

        // Save all keys without triggering per-key observer notifications
        Setting::withoutEvents(function () use ($data, $jsonFields, $boolFields) {
            foreach ($data as $key => $value) {
                if (in_array($key, $jsonFields)) {
                    Setting::set($key, json_encode($value ?? []));
                } elseif (in_array($key, $boolFields)) {
                    Setting::set($key, $value ? '1' : '0');
                } elseif (is_null($value)) {
                    Setting::set($key, '');
                } else {
                    Setting::set($key, $value);
                }
            }
        });

        // Send one consolidated notification if anything actually changed
        if (!empty($changedSections)) {
            $sectionsChanged = implode(', ', array_keys($changedSections));
            $actor = auth()->user();
            $actorName = $actor?->name ?? 'Someone';

            // Notify all other admins/staff
            $recipients = User::whereIn('role', ['admin', 'staff'])
                ->where('id', '!=', $actor?->id)
                ->get();

            foreach ($recipients as $user) {
                NotificationModel::createNotification(
                    $user,
                    'Site Settings Updated',
                    "{$actorName} updated site settings: {$sectionsChanged}.",
                    'info',
                    'setting',
                    '/admin/site-settings',
                    $actor?->id
                );
            }

            // Also notify the actor themselves so it appears in their own bell
            NotificationModel::createNotification(
                $actor,
                'Site Settings Saved',
                "You updated site settings: {$sectionsChanged}.",
                'success',
                'setting',
                '/admin/site-settings',
                $actor?->id
            );

            // Dispatch Livewire event to refresh the bell dropdown immediately
            $this->dispatch('notificationCreated');
        }

        Notification::make()
            ->title('Settings saved successfully.')
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
                ->label('Save Settings')
                ->submit('save')
                ->icon('heroicon-o-check')
                ->color('success'),
        ];
    }
}
