<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomHoldResource\Pages;
use App\Models\RoomHold;
use App\Services\RoomHoldService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RoomHoldResource extends Resource
{
    protected static ?string $model = RoomHold::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Room Holds';

    protected static ?string $modelLabel = 'Room Hold';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.room_number')
                    ->label('Room')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reservation.reference_number')
                    ->label('Reservation')
                    ->searchable()
                    ->sortable()
                    ->url(fn (RoomHold $record): string => route('filament.admin.resources.reservations.edit', $record->reservation_id)),
                Tables\Columns\TextColumn::make('reservation.guest_name')
                    ->label('Guest')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('hold_from')
                    ->label('Hold From')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hold_to')
                    ->label('Hold To')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('hold_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'advance',
                        'warning' => 'short_term',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'advance' => 'Advance',
                        'short_term' => 'Short-term',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M d, Y h:i A')
                    ->placeholder('No expiry')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (RoomHold $record): bool => ! $record->isExpired()),
            ])
            ->defaultSort('hold_from')
            ->filters([
                Tables\Filters\SelectFilter::make('hold_type')
                    ->options([
                        'advance' => 'Advance',
                        'short_term' => 'Short-term',
                    ]),
                Tables\Filters\Filter::make('is_active')
                    ->label('Active only')
                    ->toggle()
                    ->query(fn ($query) => $query->active()),
                Tables\Filters\Filter::make('holds_for_date')
                    ->label('Holds for date')
                    ->form([
                        Forms\Components\DatePicker::make('date'),
                    ])
                    ->query(function ($query, array $data) {
                        if (! isset($data['date'])) {
                            return;
                        }
                        $date = Carbon::parse($data['date']);
                        $query->where('hold_from', '<=', $date->toDateString())
                            ->where('hold_to', '>', $date->toDateString());
                    })
                    ->indicateUsing(fn (array $data): ?string => isset($data['date']) ? 'Holds for '.$data['date'] : null),
            ])
            ->actions([
                Tables\Actions\Action::make('release')
                    ->label('Release Hold')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->visible(fn (RoomHold $record) => $record->isAdvance())
                    ->requiresConfirmation()
                    ->modalHeading('Release Room Hold')
                    ->modalDescription(fn (RoomHold $record) => "Release hold on Room {$record->room->room_number} for reservation {$record->reservation->reference_number}? The room will become available for other reservations.")
                    ->action(function (RoomHold $record) {
                        $reservation = $record->reservation;
                        app(RoomHoldService::class)->releaseAdvanceHolds($reservation);

                        Notification::make()
                            ->title('Room hold released.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_release_advance')
                    ->label('Release Selected Advance Holds')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Release selected advance holds?')
                    ->modalDescription('The held rooms will become available for other reservations.')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->isAdvance()) {
                                app(RoomHoldService::class)->releaseAdvanceHolds($record->reservation);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("{$count} advance hold(s) released.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomHolds::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = RoomHold::query()
            ->advance()
            ->where('hold_from', '<=', now()->toDateString())
            ->where('hold_to', '>', now()->toDateString())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
