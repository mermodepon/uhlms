<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room_number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Occupancy')
                    ->formatStateUsing(fn ($state, \App\Models\Room $record): string =>
                        ($record->checked_in_count ?? $record->currentOccupancy()).' / '.$state
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
                        'available' => 'Available',
                        'reserved' => 'Reserved',
                        'occupied' => 'Occupied',
                        'maintenance' => 'Under Maintenance',
                        'inactive' => 'Inactive',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'reserved' => 'warning',
                        'occupied' => 'danger',
                        'maintenance' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
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
                Tables\Filters\Filter::make('has_occupants')
                    ->label('Has Occupants')
                    ->form([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Show only rooms with current guests'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['enabled'] ?? false) {
                            return $query->whereHas('roomAssignments', function (Builder $q) {
                                $q->where('status', 'checked_in')
                                    ->whereNull('checked_out_at');
                            });
                        }

                        return $query;
                    })
                    ->indicateUsing(fn (array $data): ?string => ($data['enabled'] ?? false) ? 'Has current occupants' : null),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_occupants')
                    ->label('Occupants')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->modalHeading(fn (Room $record): string => 'Current Occupants — Room ' . $record->room_number)
                    ->modalContent(fn (Room $record) => view(
                        'filament.rooms.occupants-modal',
                        [
                            'occupants' => $record->roomAssignments()
                                ->where('status', 'checked_in')
                                ->with('reservation')
                                ->orderBy('checked_in_at')
                                ->get(),
                        ]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('Room deleted')
                    ->disabled(fn (Room $record) => ($record->assignments_count ?? $record->roomAssignments()->count()) > 0)
                    ->tooltip(fn (Room $record) => ($record->assignments_count ?? $record->roomAssignments()->count()) > 0
                            ? 'This room cannot be deleted because it is linked to reservations.'
                            : null
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    // ── Set to Maintenance (admin+) ──────────────────
                    Tables\Actions\BulkAction::make('bulk_maintenance')
                        ->label('Set to Maintenance')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('warning')
                        ->visible(fn () => auth()->user()->isAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Set selected rooms to maintenance')
                        ->modalDescription('Only rooms with status "Available" will be changed. Occupied and other rooms will be skipped.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'available') {
                                    continue;
                                }
                                $record->update(['status' => 'maintenance']);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} room(s) set to maintenance")
                                ->success()
                                ->send();
                        }),

                    // ── Set to Available (admin+) ────────────────────
                    Tables\Actions\BulkAction::make('bulk_available')
                        ->label('Set to Available')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn () => auth()->user()->isAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Set selected rooms to available')
                        ->modalDescription('Only rooms with status "Under Maintenance" will be changed. Others will be skipped.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'maintenance') {
                                    continue;
                                }
                                $record->update(['status' => 'available']);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} room(s) set to available")
                                ->success()
                                ->send();
                        }),

                    // ── Deactivate (admin+) ──────────────────────────
                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->visible(fn () => auth()->user()->isAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate selected rooms')
                        ->modalDescription('Occupied rooms will be skipped.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'occupied') {
                                    continue;
                                }
                                $record->update(['is_active' => false, 'status' => 'inactive']);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} room(s) deactivated")
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
                        ->modalHeading('Activate selected rooms')
                        ->modalDescription('Only inactive rooms will be activated.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->is_active) {
                                    continue;
                                }
                                $record->update(['is_active' => true, 'status' => 'available']);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} room(s) activated")
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
                        ->modalHeading('Delete selected rooms')
                        ->modalDescription('This action is permanent. Rooms linked to reservations will be skipped. Enter your password to confirm.')
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
                                if ($record->roomAssignments()->exists()) {
                                    $skipped++;
                                    continue;
                                }
                                $record->delete();
                                $deleted++;
                            }
                            $msg = "{$deleted} room(s) deleted";
                            if ($skipped > 0) {
                                $msg .= ". {$skipped} skipped (linked to reservations).";
                            }
                            Notification::make()->title($msg)->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'roomAssignments as checked_in_count' => fn (Builder $q) => $q->where('status', 'checked_in'),
            'roomAssignments as assignments_count',
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
