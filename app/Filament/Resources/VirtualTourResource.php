<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VirtualTourResource\Pages;

use App\Models\TourWaypoint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VirtualTourResource extends Resource
{
    protected static ?string $model = TourWaypoint::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Virtual Tour';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Virtual Tour Manager';

    protected static ?string $slug = 'virtual-tour';

    protected static ?string $modelLabel = 'Scene';

    protected static ?string $pluralModelLabel = 'Scenes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Scene Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, $livewire) {
                                $set('slug', Str::slug($state));
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('type')
                            ->options([
                                'entrance' => '🚪 Entrance',
                                'lobby' => '🏛️ Lobby',
                                'hallway' => '🚶 Hallway',
                                'room-door' => '🚪 Room Door',
                                'room-interior' => '🛏️ Room Interior',
                                'amenity' => '🏊 Amenity',
                                'common-area' => '🛋️ Common Area',
                            ])
                            ->required()
                            ->default('entrance')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('position_order')
                            ->label('Order')
                            ->required()
                            ->numeric()
                            ->default(fn (): int => ((int) TourWaypoint::max('position_order')) + 1)
                            ->helperText('Lower numbers appear first'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Images')
                    ->schema([
                        Forms\Components\FileUpload::make('panorama_image')
                            ->label('360° Panorama Image')
                            ->image()
                            ->previewable(false)
                            ->disk(config('media.disk'))
                            ->directory('virtual-tour/panoramas')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->required()
                            ->helperText('Upload equirectangular 360° panorama (max 10MB)'),
                        Forms\Components\FileUpload::make('thumbnail_image')
                            ->label('Thumbnail')
                            ->image()
                            ->disk(config('media.disk'))
                            ->directory('virtual-tour/thumbnails')
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->maxSize(2048)
                            ->helperText('Small image for mini-map (optional)'),
                    ]),

                Forms\Components\Section::make('Room Linking')
                    ->description('Associate this waypoint with a room type or specific room. If both are set, the specific room takes priority.')
                    ->schema([
                        Forms\Components\Select::make('linked_room_type_id')
                            ->label('Link to Room Type')
                            ->relationship('roomType', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('linked_room_id', null))
                            ->helperText('Select a room type to show room information during the tour'),
                        Forms\Components\Select::make('linked_room_id')
                            ->label('Specific Room (Optional)')
                            ->relationship('room', 'room_number')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->visible(fn ($get) => filled($get('linked_room_type_id')))
                            ->options(function ($get) {
                                $roomTypeId = $get('linked_room_type_id');
                                if (!$roomTypeId) {
                                    return [];
                                }
                                return \App\Models\Room::where('room_type_id', $roomTypeId)
                                    ->where('is_active', true)
                                    ->orderBy('room_number')
                                    ->pluck('room_number', 'id')
                                    ->toArray();
                            })
                            ->helperText('🔒 Link to a specific room for internal tracking. Guests see the room type name and general availability only (room numbers are hidden for security).'),
                    ]),

                Forms\Components\Section::make('Description & Narration')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Brief description of this location'),
                        Forms\Components\Textarea::make('narration')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Auto-displayed tooltip when user reaches this scene'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_image')
                    ->label('Preview')
                    ->size(40)
                    ->disk(config('media.disk'))
                    ->getStateUsing(fn (TourWaypoint $record) => $record->thumbnail_image ?: $record->panorama_image),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'entrance' => '🚪 Entrance',
                        'lobby' => '🏛️ Lobby',
                        'hallway' => '🚶 Hallway',
                        'room-door' => '🚪 Room Door',
                        'room-interior' => '🛏️ Room Interior',
                        'amenity' => '🏊 Amenity',
                        'common-area' => '🛋️ Common Area',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('position_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('roomType.name')
                    ->label('Room Type')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('hotspots_count')
                    ->label('Hotspots')
                    ->badge()
                    ->counts('hotspots')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'entrance' => 'Entrance',
                        'lobby' => 'Lobby',
                        'hallway' => 'Hallway',
                        'room-door' => 'Room Door',
                        'room-interior' => 'Room Interior',
                        'amenity' => 'Amenity',
                        'common-area' => 'Common Area',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => true]);
                            }
                            Notification::make()
                                ->title('Selected scenes activated')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => false]);
                            }
                            Notification::make()
                                ->title('Selected scenes deactivated')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position_order')
            ->defaultSort('position_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVirtualTours::route('/'),
            'create' => Pages\CreateVirtualTour::route('/create'),
            'edit' => Pages\EditVirtualTour::route('/{record}/edit'),
            'manage-hotspots' => Pages\ManageTourHotspots::route('/{record}/hotspots'),
        ];
    }
}
