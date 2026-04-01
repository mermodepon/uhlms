<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use App\Models\ReservationLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StayLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Activity Log';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('logged_at', 'desc')
            ->paginated([15, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('logged_at')
                    ->label('Date & Time')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->width('180px'),
                Tables\Columns\BadgeColumn::make('event')
                    ->label('Event')
                    ->formatStateUsing(fn (string $state) => ReservationLog::eventLabel($state))
                    ->color(fn (string $state) => ReservationLog::eventColor($state))
                    ->width('170px'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Details')
                    ->wrap(),
                Tables\Columns\TextColumn::make('actor_name')
                    ->label('By')
                    ->placeholder('System')
                    ->width('140px'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Event Type')
                    ->options(fn () => collect([
                        'reservation_created',
                        'reservation_approved',
                        'reservation_declined',
                        'reservation_cancelled',
                        'reservation_checked_out',
                        'checkin_hold_prepared',
                        'checkin_hold_released',
                        'checkin_hold_expired',
                        'checkin_finalized',
                        'guest_checked_in',
                        'guest_checked_out',
                        'room_assignment_removed',
                    ])->mapWithKeys(fn ($e) => [$e => ReservationLog::eventLabel($e)])->all()),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
