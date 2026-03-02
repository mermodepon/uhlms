<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Room Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Room Information')
                    ->schema([
                        Forms\Components\TextInput::make('room_number')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('room_type_id')
                            ->relationship('roomType', 'name')
                            ->required()
                            ->preload()
                            ->searchable(),
                        Forms\Components\Select::make('floor_id')
                            ->relationship('floor', 'name')
                            ->required()
                            ->preload()
                            ->searchable(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'available' => 'Available',
                                'occupied' => 'Occupied',
                                'maintenance' => 'Under Maintenance',
                                'inactive' => 'Inactive',
                            ])
                            ->default('available')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room_number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('roomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('floor.name')
                    ->label('Floor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available'   => 'Available',
                        'occupied'    => 'Occupied',
                        'maintenance' => 'Under Maintenance',
                        'inactive'    => 'Inactive',
                        default       => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'available'   => 'success',
                        'occupied'    => 'danger',
                        'maintenance' => 'warning',
                        'inactive'    => 'gray',
                        default       => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('room_number')
            ->filters([
                Tables\Filters\SelectFilter::make('room_type_id')
                    ->relationship('roomType', 'name')
                    ->label('Room Type')
                    ->preload(),
                Tables\Filters\SelectFilter::make('floor_id')
                    ->relationship('floor', 'name')
                    ->label('Floor')
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'occupied' => 'Occupied',
                        'maintenance' => 'Under Maintenance',
                        'inactive' => 'Inactive',
                        'checked_out' => 'Checked out',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('Room deleted')
                    ->disabled(fn (Room $record) => $record->roomAssignments()->exists() || $record->stayLogs()->exists())
                    ->tooltip(fn (Room $record) =>
                        ($record->roomAssignments()->exists() || $record->stayLogs()->exists())
                            ? 'This room cannot be deleted because it is linked to reservations or stay logs.'
                            : null
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->successNotificationTitle('Rooms deleted'),
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
