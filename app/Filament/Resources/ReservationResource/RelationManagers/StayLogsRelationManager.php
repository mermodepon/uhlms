<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Stay Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('room.room_number')
                            ->label('Room'),
                        Infolists\Components\TextEntry::make('room.roomType.name')
                            ->label('Room Type'),
                        Infolists\Components\TextEntry::make('room.floor.name')
                            ->label('Floor'),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Check-in Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('checked_in_at')
                            ->label('Date & Time')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('checkedInByUser.name')
                            ->label('Processed By'),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Check-out Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('checked_out_at')
                            ->label('Date & Time')
                            ->dateTime()
                            ->placeholder('Not checked out yet'),
                        Infolists\Components\TextEntry::make('checkedOutByUser.name')
                            ->label('Processed By')
                            ->placeholder('—'),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Remarks')
                    ->schema([
                        Infolists\Components\TextEntry::make('remarks')
                            ->label('')
                            ->placeholder('No remarks')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.room_number')
                    ->label('Room')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('checked_in_at')
                    ->dateTime()
                    ->label('Checked In')
                    ->sortable(),
                Tables\Columns\TextColumn::make('checkedInByUser.name')
                    ->label('By')
                    ->sortable()
                    ->searchable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('checked_out_at')
                    ->dateTime()
                    ->label('Checked Out')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('checkedOutByUser.name')
                    ->label('By')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—')
                    ->default('—'),
                Tables\Columns\TextColumn::make('remarks')
                    ->limit(40)
                    ->wrap()
                    ->toggleable(),
            ])
            ->defaultSort('checked_in_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['room', 'checkedInByUser', 'checkedOutByUser']))
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }
}
