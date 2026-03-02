<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages;
use App\Models\Message;
use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_read', false)
            ->whereIn('sender_type', ['guest'])
            ->count();

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
                Forms\Components\Section::make('Message Details')
                    ->schema([
                        Forms\Components\Select::make('reservation_id')
                            ->label('Reservation')
                            ->relationship('reservation', 'reference_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('sender_id')
                            ->default(auth()->id()),
                        Forms\Components\Hidden::make('sender_type')
                            ->default(auth()->user()?->role ?? 'staff'),
                    ]),
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
                    ->label('Guest Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sender_display_name')
                    ->label('From')
                    ->searchable(['sender_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('sender_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'guest' => 'success',
                        'staff' => 'info',
                        'admin' => 'primary',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_read')
                    ->boolean()
                    ->sortable()
                    ->label('Read'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->searchable()
                    ->label('Sent'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('sender_type')
                    ->options([
                        'guest' => 'Guest',
                        'staff' => 'Staff',
                        'admin' => 'Admin',
                    ]),
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('Read Status'),
                Tables\Filters\SelectFilter::make('reservation_id')
                    ->relationship('reservation', 'reference_number')
                    ->searchable()
                    ->preload()
                    ->label('Reservation'),
            ])
            ->actions([
                Tables\Actions\Action::make('reply')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('Reply')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (Message $record, array $data) {
                        Message::create([
                            'reservation_id' => $record->reservation_id,
                            'sender_id' => auth()->id(),
                            'sender_type' => auth()->user()->role,
                            'message' => $data['message'],
                        ]);
                        
                        if (!$record->is_read) {
                            $record->markAsRead();
                        }
                    })
                    ->successNotificationTitle('Reply sent'),
                Tables\Actions\Action::make('markAsRead')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Message $record) => !$record->is_read)
                    ->action(fn (Message $record) => $record->markAsRead())
                    ->successNotificationTitle('Marked as read'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('Message deleted'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markAsRead')
                        ->label('Mark as read')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->markAsRead())
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->successNotificationTitle('Messages deleted'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessages::route('/'),
            'create' => Pages\CreateMessage::route('/create'),
            'view' => Pages\ViewMessage::route('/{record}'),
        ];
    }
}

