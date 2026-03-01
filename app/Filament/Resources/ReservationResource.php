<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\StayLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Guest Information')
                    ->schema([
                        Forms\Components\TextInput::make('guest_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('guest_email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('guest_phone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('guest_organization')
                            ->maxLength(255)
                            ->placeholder('e.g., CMU Biology Department'),
                        Forms\Components\Textarea::make('guest_address')
                            ->rows(2)
                            ->columnSpanFull(),
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
                            ->searchable(),
                        Forms\Components\DatePicker::make('check_in_date')
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('check_out_date')
                            ->required()
                            ->after('check_in_date')
                            ->native(false),
                        Forms\Components\TextInput::make('number_of_occupants')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(1),
                        Forms\Components\Select::make('purpose')
                            ->options([
                                'academic' => 'Academic',
                                'official' => 'Official Business',
                                'personal' => 'Personal',
                                'event' => 'Event / Conference',
                                'other' => 'Other',
                            ]),
                        Forms\Components\Textarea::make('special_requests')
                            ->rows(3)
                            ->columnSpanFull(),
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
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Staff Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('guest_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guest_email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('preferredRoomType.name')
                    ->label('Room Type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'declined' => 'danger',
                        'cancelled' => 'gray',
                        'checked_in' => 'success',
                        'checked_out' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Submitted'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'declined' => 'Declined',
                        'cancelled' => 'Cancelled',
                        'checked_in' => 'Checked In',
                        'checked_out' => 'Checked Out',
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
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Approve action
                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Reservation')
                        ->modalDescription('Are you sure you want to approve this reservation?')
                        ->visible(fn (Reservation $record) => $record->status === 'pending')
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Notes (optional)')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->update([
                                'status' => 'approved',
                                'admin_notes' => $data['admin_notes'] ?? $record->admin_notes,
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
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
                            $record->update([
                                'status' => 'declined',
                                'admin_notes' => $data['admin_notes'],
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        }),

                    // Assign Room action
                    Tables\Actions\Action::make('assign_room')
                        ->icon('heroicon-o-key')
                        ->color('info')
                        ->modalHeading('Assign Room')
                        ->visible(fn (Reservation $record) => $record->status === 'approved' && $record->roomAssignments->isEmpty())
                        ->form([
                            Forms\Components\Select::make('room_id')
                                ->label('Select Room')
                                ->options(function (Reservation $record) {
                                    return Room::where('status', 'available')
                                        ->where('is_active', true)
                                        ->where('room_type_id', $record->preferred_room_type_id)
                                        ->with('floor')
                                        ->get()
                                        ->mapWithKeys(fn ($room) => [
                                            $room->id => "Room {$room->room_number} ({$room->floor->name})",
                                        ]);
                                })
                                ->required()
                                ->searchable(),
                            Forms\Components\Textarea::make('notes')
                                ->label('Assignment Notes')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            RoomAssignment::create([
                                'reservation_id' => $record->id,
                                'room_id' => $data['room_id'],
                                'assigned_by' => auth()->id(),
                                'assigned_at' => now(),
                                'notes' => $data['notes'] ?? null,
                            ]);
                        }),

                    // Check In action
                    Tables\Actions\Action::make('check_in')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Check In Guest')
                        ->visible(fn (Reservation $record) => $record->status === 'approved' && $record->roomAssignments->isNotEmpty())
                        ->form([
                            Forms\Components\Textarea::make('remarks')
                                ->label('Check-in Remarks')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->update(['status' => 'checked_in']);

                            foreach ($record->roomAssignments as $assignment) {
                                StayLog::create([
                                    'reservation_id' => $record->id,
                                    'room_id' => $assignment->room_id,
                                    'checked_in_at' => now(),
                                    'checked_in_by' => auth()->id(),
                                    'remarks' => $data['remarks'] ?? null,
                                ]);
                                $assignment->room->update(['status' => 'occupied']);
                            }
                        }),

                    // Check Out action
                    Tables\Actions\Action::make('check_out')
                        ->icon('heroicon-o-arrow-left-on-rectangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Check Out Guest')
                        ->visible(fn (Reservation $record) => $record->status === 'checked_in')
                        ->form([
                            Forms\Components\Textarea::make('remarks')
                                ->label('Check-out Remarks')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->update(['status' => 'checked_out']);

                            foreach ($record->stayLogs()->whereNull('checked_out_at')->get() as $log) {
                                $log->update([
                                    'checked_out_at' => now(),
                                    'checked_out_by' => auth()->id(),
                                    'remarks' => $data['remarks'] ? ($log->remarks ? $log->remarks . ' | ' . $data['remarks'] : $data['remarks']) : $log->remarks,
                                ]);
                                $log->room->update(['status' => 'available']);
                            }
                        }),

                    // Cancel action
                    Tables\Actions\Action::make('cancel')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Reservation $record) => in_array($record->status, ['pending', 'approved']))
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Cancellation reason')
                                ->required()
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->update([
                                'status' => 'cancelled',
                                'admin_notes' => $data['admin_notes'],
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReservationResource\RelationManagers\RoomAssignmentsRelationManager::class,
            ReservationResource\RelationManagers\StayLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'view' => Pages\ViewReservation::route('/{record}'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
