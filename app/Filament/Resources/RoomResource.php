<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
                            ->searchable()
                            ->live(),
                        Forms\Components\Select::make('floor_id')
                            ->relationship('floor', 'name')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->default(function () {
                                return \App\Models\Floor::where('name', 'Ground Floor')->first()?->id 
                                    ?? \App\Models\Floor::orderBy('level')->first()?->id;
                            }),
                        Forms\Components\TextInput::make('capacity')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(10)
                            ->helperText('Maximum number of guests this room can accommodate.'),
                        Forms\Components\Select::make('gender_type')
                            ->label('Gender Restriction')
                            ->options([
                                'male'   => 'Male Only',
                                'female' => 'Female Only',
                                'any'    => 'Any Gender',
                            ])
                            ->default('any')
                            ->required()
                            ->native(false)
                            ->helperText('Restricts room assignment to guests of the selected gender.'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'available' => 'Available',
                                'reserved' => 'Reserved (Pending Payment)',
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

                Forms\Components\Section::make('Beds Configuration')
                    ->description('Define the individual beds inside this room. Each bed can be assigned to one guest during check-in.')
                    ->collapsible()
                    ->hidden(function (Get $get): bool {
                        $roomTypeId = $get('room_type_id');
                        if (! $roomTypeId) {
                            return false;
                        }
                        return \App\Models\RoomType::find($roomTypeId)?->isPrivate() ?? false;
                    })
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('bulkAddBeds')
                                ->label('Bulk Add Beds')
                                ->icon('heroicon-o-sparkles')
                                ->color('info')
                                ->modalHeading('Bulk Add Beds')
                                ->modalDescription("Generate multiple beds at once. Labels are built as: Prefix + Number (e.g. prefix \"Bed\", count 10 → Bed 1, Bed 2 … Bed 10).")
                                ->modalWidth('md')
                                ->form([
                                    Forms\Components\TextInput::make('prefix')
                                        ->label('Label Prefix')
                                        ->placeholder('e.g. Bed, Lower, Upper, Bunk A')
                                        ->default('Bed')
                                        ->required()
                                        ->maxLength(30)
                                        ->helperText('A space is automatically added between the prefix and the number.'),
                                    Forms\Components\TextInput::make('start')
                                        ->label('Starting Number')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->required(),
                                    Forms\Components\TextInput::make('count')
                                        ->label('Number of Beds to Generate')
                                        ->numeric()
                                        ->default(10)
                                        ->minValue(1)
                                        ->maxValue(50)
                                        ->required()
                                        ->helperText('Maximum 50 beds per bulk operation.'),
                                    Forms\Components\Select::make('default_status')
                                        ->label('Default Status')
                                        ->options([
                                            'available' => 'Available',
                                            'reserved'  => 'Reserved',
                                            'occupied'  => 'Occupied',
                                        ])
                                        ->default('available')
                                        ->required()
                                        ->native(false),
                                ])
                                ->action(function (array $data, Get $get, Set $set): void {
                                    $prefix   = trim($data['prefix']);
                                    $start    = (int) $data['start'];
                                    $count    = (int) $data['count'];
                                    $status   = $data['default_status'];
                                    $current  = $get('beds') ?? [];
                                    $newItems = [];

                                    for ($i = $start; $i < $start + $count; $i++) {
                                        $newItems[(string) Str::uuid()] = [
                                            'bed_number' => $prefix . ' ' . $i,
                                            'status'     => $status,
                                        ];
                                    }

                                    $set('beds', array_merge($current, $newItems));
                                }),
                        ])->columnSpanFull(),

                        Forms\Components\Repeater::make('beds')
                            ->relationship('beds')
                            ->schema([
                                Forms\Components\TextInput::make('bed_number')
                                    ->label('Bed Label')
                                    ->required()
                                    ->placeholder('e.g. Bed 1, Lower A1, Upper B2')
                                    ->maxLength(50),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'available' => 'Available',
                                        'reserved'  => 'Reserved',
                                        'occupied'  => 'Occupied',
                                    ])
                                    ->default('available')
                                    ->required()
                                    ->native(false),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ Add Single Bed')
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => $state['bed_number'] ?? null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room_number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender_type')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'male'   => 'Male',
                        'female' => 'Female',
                        default  => 'Any',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'male'   => 'info',
                        'female' => 'danger',
                        default  => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->formatStateUsing(fn ($state, \App\Models\Room $record): string =>
                        ! $record->roomType?->isPrivate() && $record->beds()->exists()
                            ? $record->availableBedsCount() . ' / ' . $record->beds()->count() . ' beds free'
                            : $state . ' capacity'
                    )
                    ->searchable()
                    ->sortable(),
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
                        'reserved'    => 'Reserved',
                        'occupied'    => 'Occupied',
                        'maintenance' => 'Under Maintenance',
                        'inactive'    => 'Inactive',
                        default       => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'available'   => 'success',
                        'reserved'    => 'warning',
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
                        'reserved' => 'Reserved',
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
                    ->disabled(fn (Room $record) => $record->roomAssignments()->exists())
                    ->tooltip(fn (Room $record) =>
                        $record->roomAssignments()->exists()
                            ? 'This room cannot be deleted because it is linked to reservations.'
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if ($status = request()->query('status')) {
            $query->where('status', $status);
        }

        return $query;
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
