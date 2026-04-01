<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomTypeResource\Pages;
use App\Models\RoomType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoomTypeResource extends Resource
{
    protected static ?string $model = RoomType::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Room Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Room Type Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('base_rate')
                            ->required()
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0)
                            ->label('Base Rate')
                            ->helperText(fn ($get) => 
                                $get('pricing_type') === 'per_person' 
                                    ? 'Rate per person per night'
                                    : 'Rate per night'
                            ),
                        Forms\Components\Select::make('pricing_type')
                            ->label('Pricing Type')
                            ->options([
                                'flat_rate' => 'Flat Rate (per room)',
                                'per_person' => 'Per Person',
                            ])
                            ->default('flat_rate')
                            ->required()
                            ->live()
                            ->helperText('Choose how this room type is priced'),
                        Forms\Components\Select::make('room_sharing_type')
                            ->label('Room Sharing Type')
                            ->options([
                                'public'  => 'Public / Shared (dormitory-style)',
                                'private' => 'Private (exclusive to one reservation)',
                            ])
                            ->default('private')
                            ->required()
                            ->helperText(
                                'Public: multiple guests can share the room up to capacity. '
                              . 'Private: once a guest checks in, the room is locked exclusively for that reservation.'
                            ),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Amenities')
                    ->schema([
                        Forms\Components\Select::make('amenities')
                            ->relationship('amenities', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\Textarea::make('description')->rows(2),
                            ]),
                    ]),

                Forms\Components\Section::make('Media & Virtual Tour')
                    ->schema([
                        Forms\Components\FileUpload::make('images')
                            ->image()
                            ->multiple()
                            ->maxFiles(5)
                            ->reorderable()
                            ->directory('room-types')
                            ->helperText('Upload up to 5 images. Drag to reorder.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('virtual_tour_url')
                            ->label('Virtual Tour URL')
                            ->url()
                            ->placeholder('https://pfrm.panoee.com/...')
                            ->helperText('Paste the Panoee virtual tour embed URL here')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_rate')
                    ->label('Rate')
                    ->formatStateUsing(fn (RoomType $record) => $record->getFormattedPrice())
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pricing_type')
                    ->label('Pricing')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'flat_rate' => 'Flat Rate',
                        'per_person' => 'Per Person',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'flat_rate' => 'primary',
                        'per_person' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('room_sharing_type')
                    ->label('Sharing')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'private' => 'Private',
                        'public'  => 'Public / Shared',
                        default   => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'private' => 'warning',
                        'public'  => 'success',
                        default   => 'gray',
                    }),
                Tables\Columns\TextColumn::make('rooms.room_number')
                    ->label('Rooms')
                    ->searchable()
                    ->badge()
                    ->separator(',')
                    ->limitList(5)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('amenities.name')
                    ->searchable()
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->label('Amenities'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('virtual_tour_url')
                    ->label('Tour')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->virtual_tour_url)),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('Room Type deleted')
                    ->disabled(fn (RoomType $record) => $record->rooms()->exists())
                    ->tooltip(fn (RoomType $record) =>
                        $record->rooms()->exists()
                            ? 'This room type cannot be deleted because it is linked to rooms.'
                            : null
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->successNotificationTitle('Room Types deleted'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomTypes::route('/'),
            'create' => Pages\CreateRoomType::route('/create'),
            'edit' => Pages\EditRoomType::route('/{record}/edit'),
        ];
    }
}
