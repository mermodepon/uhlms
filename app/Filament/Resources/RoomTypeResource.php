<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomTypeResource\Pages;
use App\Models\RoomType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
                            ->helperText(fn ($get) => $get('pricing_type') === 'per_person'
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
                                'public' => 'Public / Shared (dormitory-style)',
                                'private' => 'Private (exclusive to one reservation)',
                            ])
                            ->default('private')
                            ->required()
                            ->helperText(
                                'Public: multiple guests can share the room up to capacity. '
                              .'Private: once a guest checks in, the room is locked exclusively for that reservation.'
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

                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('images')
                            ->image()
                            ->multiple()
                            ->disk(config('media.disk'))
                            ->maxFiles(5)
                            ->reorderable()
                            ->directory('room-types')
                            ->helperText('Upload up to 5 images. Drag to reorder.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->disk(config('media.disk'))
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
                        'public' => 'Public / Shared',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'private' => 'warning',
                        'public' => 'success',
                        default => 'gray',
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
                    ->tooltip(fn (RoomType $record) => $record->rooms()->exists()
                            ? 'This room type cannot be deleted because it is linked to rooms.'
                            : null
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    // ── Deactivate (admin+) ──────────────────────────
                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->visible(fn () => auth()->user()->isAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate selected room types')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (! $record->is_active) {
                                    continue;
                                }
                                $record->update(['is_active' => false]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} room type(s) deactivated")
                                ->success()
                                ->send();
                        }),

                    // ── Activate (admin+) ────────────────────────────
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn () => auth()->user()->isAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Activate selected room types')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->is_active) {
                                    continue;
                                }
                                $record->update(['is_active' => true]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} room type(s) activated")
                                ->success()
                                ->send();
                        }),

                    // ── Bulk Delete (super_admin + password) ─────────
                    Tables\Actions\BulkAction::make('bulk_delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn () => auth()->user()->isSuperAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected room types')
                        ->modalDescription('This action is permanent. Room types with linked rooms will be skipped. Enter your password to confirm.')
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
                            $deleted = 0;
                            $skipped = 0;
                            foreach ($records as $record) {
                                if ($record->rooms()->exists()) {
                                    $skipped++;
                                    continue;
                                }
                                $record->delete();
                                $deleted++;
                            }
                            $msg = "{$deleted} room type(s) deleted";
                            if ($skipped > 0) {
                                $msg .= ". {$skipped} skipped (have linked rooms).";
                            }
                            Notification::make()->title($msg)->success()->send();
                        }),
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
