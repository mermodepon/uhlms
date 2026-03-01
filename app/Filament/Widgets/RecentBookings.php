<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentBookings extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Reservations';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reservation::query()
                    ->with(['preferredRoomType'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('guest_name')
                    ->label('Guest'),
                Tables\Columns\TextColumn::make('preferredRoomType.name')
                    ->label('Room Type'),
                Tables\Columns\TextColumn::make('check_in_date')
                    ->date(),
                Tables\Columns\TextColumn::make('check_out_date')
                    ->date(),
                Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'checked_out' => 'Checked out',
                            'checked_in' => 'Checked in',
                            default => ucfirst(str_replace('_', ' ', $state)),
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'pending' => 'warning',
                            'approved' => 'info',
                            'declined' => 'danger',
                            'cancelled' => 'gray',
                            'checked_in' => 'success',
                            'checked_out' => 'gray',
                            default => 'gray',
                        }),
            ])
            ->paginated(false);
    }
}
