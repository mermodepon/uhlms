<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\Pages;
use App\Models\Message;
use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ConversationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Conversations';

    protected static ?string $modelLabel = 'Conversation';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        // Count reservations with unread messages
        $count = Reservation::whereHas('messages', function ($query) {
            $query->where('is_read', false)
                ->where('sender_type', 'guest');
        })->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reservation')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('guest_name')
                    ->label('Guest')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('messages_count')
                    ->label('Messages')
                    ->counts('messages')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('unread_messages_count')
                    ->label('Unread')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function (Reservation $record) {
                        return $record->messages()
                            ->where('is_read', false)
                            ->where('sender_type', 'guest')
                            ->count();
                    }),
                Tables\Columns\TextColumn::make('last_message')
                    ->label('Last Message')
                    ->limit(60)
                    ->getStateUsing(function (Reservation $record) {
                        $lastMessage = $record->messages()->latest()->first();
                        return $lastMessage ? $lastMessage->message : 'No messages yet';
                    }),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Last Activity')
                    ->getStateUsing(function (Reservation $record) {
                        $lastMessage = $record->messages()->latest()->first();
                        return $lastMessage ? $lastMessage->created_at : null;
                    })
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                // Only show reservations that have messages
                $query->has('messages')
                    ->with('messages')
                    ->withCount('messages')
                    ->addSelect([
                        'last_message_at' => Message::select('created_at')
                            ->whereColumn('reservation_id', 'reservations.id')
                            ->latest()
                            ->limit(1)
                    ]);
            })
            ->filters([
                Tables\Filters\Filter::make('has_unread')
                    ->label('Has Unread Messages')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('messages', function ($q) {
                            $q->where('is_read', false)
                                ->where('sender_type', 'guest');
                        })
                    )
                    ->toggle(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'checked_out' => 'Checked Out',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_conversation')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Reservation $record): string => 
                        ConversationResource::getUrl('view', ['record' => $record->id])
                    ),
                Tables\Actions\Action::make('delete_messages')
                    ->label('Delete Messages')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete All Messages')
                    ->modalDescription('Are you sure you want to delete all messages in this conversation? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete messages')
                    ->action(function (Reservation $record) {
                        $count = $record->messages()->count();
                        $record->messages()->delete();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Messages deleted')
                            ->body("{$count} message(s) deleted from conversation {$record->reference_number}.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Reservation $record) => $record->messages()->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_all_read')
                        ->label('Mark All Messages as Read')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $reservation) {
                                $reservation->messages()
                                    ->where('is_read', false)
                                    ->update([
                                        'is_read' => true,
                                        'read_at' => now(),
                                    ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('All messages marked as read'),
                    Tables\Actions\BulkAction::make('delete_all_messages')
                        ->label('Delete All Messages')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete All Messages')
                        ->modalDescription('Are you sure you want to delete all messages in the selected conversations? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete all')
                        ->action(function ($records) {
                            $totalDeleted = 0;
                            foreach ($records as $reservation) {
                                $count = $reservation->messages()->count();
                                $reservation->messages()->delete();
                                $totalDeleted += $count;
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Messages deleted successfully')
                                ->body("{$totalDeleted} message(s) deleted from selected conversations.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
