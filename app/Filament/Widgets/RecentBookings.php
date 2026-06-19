<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentBookings extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Reservations';

    public static function canView(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reservation::query()
                    ->with('preferredRoomType')
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
                    ->formatStateUsing(fn ($state, $record) => match ($state) {
                        'approved' => 'Approved',
                        'confirmed' => 'Confirmed',
                        'checked_out' => 'Checked Out',
                        'checked_in' => 'Checked In',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn ($state): array => match ((string) $state) {
                        'pending' => Color::hex('#fbbf24'),
                        'approved' => Color::hex('#919F02'),
                        'confirmed' => Color::hex('#10B981'),
                        'declined' => Color::hex('#EF4444'),
                        'cancelled' => Color::hex('#6B7280'),
                        'checked_in' => Color::hex('#16a34a'),
                        'checked_out' => Color::hex('#94a3b8'),
                        default => Color::hex('#6B7280'),
                    }),
            ])
            ->paginated(false);
    }
}
