<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StayLogResource\Pages;
use App\Models\StayLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StayLogResource extends Resource
{
    protected static ?string $model = StayLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Stay Log';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Stay Log Details')
                    ->schema([
                        Forms\Components\Select::make('reservation_id')
                            ->relationship('reservation', 'reference_number')
                            ->required()
                            ->preload()
                            ->searchable(),
                        Forms\Components\Select::make('room_id')
                            ->relationship('room', 'room_number')
                            ->required()
                            ->preload()
                            ->searchable(),
                        Forms\Components\DateTimePicker::make('checked_in_at'),
                        Forms\Components\DateTimePicker::make('checked_out_at'),
                        Forms\Components\Textarea::make('remarks')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reservation.reference_number')
                    ->label('Reservation')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reservation.guest_name')
                    ->label('Guest')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.room_number')
                    ->label('Room')
                    ->sortable(),
                Tables\Columns\TextColumn::make('checked_in_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Checked In'),
                Tables\Columns\TextColumn::make('checkedInByUser.name')
                    ->label('By')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('checked_out_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Checked Out')
                    ->placeholder('Still checked in'),
                Tables\Columns\TextColumn::make('checkedOutByUser.name')
                    ->label('By')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('remarks')
                    ->limit(30)
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('checked_in_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('currently_checked_in')
                    ->label('Currently Checked In')
                    ->query(fn (Builder $query) => $query->whereNotNull('checked_in_at')->whereNull('checked_out_at')),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('checked_in_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('checked_in_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStayLogs::route('/'),
            'create' => Pages\CreateStayLog::route('/create'),
            'edit' => Pages\EditStayLog::route('/{record}/edit'),
        ];
    }
}
