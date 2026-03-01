<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StayLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'stayLogs';

    protected static ?string $title = 'Stay Logs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('room_id')
                    ->relationship('room', 'room_number')
                    ->required()
                    ->preload(),
                Forms\Components\DateTimePicker::make('checked_in_at'),
                Forms\Components\DateTimePicker::make('checked_out_at'),
                Forms\Components\Textarea::make('remarks')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.room_number')
                    ->label('Room'),
                Tables\Columns\TextColumn::make('checked_in_at')
                    ->dateTime()
                    ->label('Checked In'),
                Tables\Columns\TextColumn::make('checkedInByUser.name')
                    ->label('By'),
                Tables\Columns\TextColumn::make('checked_out_at')
                    ->dateTime()
                    ->label('Checked Out')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('checkedOutByUser.name')
                    ->label('By')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('remarks')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }
}
