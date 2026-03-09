<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 99;

    protected static ?string $label = 'Site Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Site Branding')
                    ->description('General site identity and appearance')
                    ->schema([
                        Forms\Components\TextInput::make('site_title')->label('Site Title')->maxLength(60),
                        Forms\Components\TextInput::make('site_tagline')->label('Tagline')->maxLength(120),
                        Forms\Components\FileUpload::make('site_logo')->label('Logo')->image()->disk('public')->directory('images')->visibility('public'),
                        Forms\Components\ColorPicker::make('theme_color')->label('Theme Color'),
                        Forms\Components\Select::make('theme_font')->label('Font')->options([
                            'sans' => 'Sans Serif',
                            'serif' => 'Serif',
                            'mono' => 'Monospace',
                        ]),
                    ]),

                Forms\Components\Section::make('Contact & Social')
                    ->description('How guests can reach you')
                    ->schema([
                        Forms\Components\TextInput::make('contact_phone')->label('Phone')->maxLength(30),
                        Forms\Components\TextInput::make('contact_email')->label('Email')->maxLength(60),
                        Forms\Components\TextInput::make('contact_address')->label('Address')->maxLength(120),
                        Forms\Components\TextInput::make('contact_map_embed')->label('Google Maps Embed')->maxLength(255),
                        Forms\Components\TextInput::make('social_facebook')->label('Facebook URL')->maxLength(120),
                        Forms\Components\TextInput::make('social_instagram')->label('Instagram URL')->maxLength(120),
                        Forms\Components\TextInput::make('social_twitter')->label('Twitter URL')->maxLength(120),
                    ]),

                Forms\Components\Section::make('Hero & Welcome')
                    ->description('Homepage hero image and welcome message')
                    ->schema([
                        Forms\Components\FileUpload::make('hero_banner')->label('Hero Banner')->image()->disk('public')->directory('images')->visibility('public'),
                        Forms\Components\Toggle::make('hero_banner_embed_enabled')
                            ->label('Use Embed for Hero')
                            ->helperText('When enabled, the hero will render the embed URL instead of the hero image.'),
                        Forms\Components\TextInput::make('hero_banner_embed')
                            ->label('Hero Banner Embed URL')
                            ->url()
                            ->placeholder('https://tour.panoee.net/...')
                            ->helperText('Paste a Panoee or other virtual-tour URL to embed in the hero. If set, this will be used instead of the static hero image.')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('welcome_message')->label('Welcome Message')->maxLength(300),
                    ]),

                Forms\Components\Section::make('About & Amenities')
                    ->description('About your lodging and amenities')
                    ->schema([
                        Forms\Components\Textarea::make('about_text')->label('About Section')->maxLength(500),
                        Forms\Components\Toggle::make('show_amenities')->label('Show Amenities'),
                        Forms\Components\Repeater::make('amenities')->label('Amenities')
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Amenity Name')->maxLength(40),
                                Forms\Components\FileUpload::make('image')->label('Image')->image()->disk('public')->directory('images')->visibility('public'),
                            ]),
                    ]),

                Forms\Components\Section::make('Booking Policy & FAQ')
                    ->description('Booking terms and common questions')
                    ->schema([
                        Forms\Components\Textarea::make('booking_policy')->label('Booking Policy & Terms')->maxLength(500),
                        Forms\Components\Repeater::make('faq')->label('FAQ')
                            ->schema([
                                Forms\Components\TextInput::make('question')->label('Question')->maxLength(120),
                                Forms\Components\Textarea::make('answer')->label('Answer')->maxLength(300),
                            ]),
                    ]),

                Forms\Components\Section::make('Announcement Bar')
                    ->description('Urgent notices, promos, or events')
                    ->schema([
                        Forms\Components\Toggle::make('show_announcement')->label('Show Announcement'),
                        Forms\Components\Textarea::make('announcement_text')->label('Announcement Text')->maxLength(200),
                    ]),

                Forms\Components\Section::make('Maintenance & Accessibility')
                    ->description('Site status and accessibility options')
                    ->schema([
                        Forms\Components\Toggle::make('maintenance_mode')->label('Enable Maintenance Mode'),
                        Forms\Components\Textarea::make('maintenance_message')->label('Maintenance Message')->maxLength(200),
                        Forms\Components\Toggle::make('accessibility_high_contrast')->label('High Contrast Mode'),
                        Forms\Components\Toggle::make('accessibility_large_text')->label('Large Text Mode'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->limit(50)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('Setting deleted'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->successNotificationTitle('Settings deleted'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
        ];
    }
}
