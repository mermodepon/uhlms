<?php

namespace App\Filament\Resources\MessageResource\Pages;

use App\Filament\Resources\MessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\Textarea::make('message')
                        ->label('Reply Message')
                        ->required()
                        ->rows(5),
                ])
                ->action(function (array $data) {
                    \App\Models\Message::create([
                        'reservation_id' => $this->record->reservation_id,
                        'sender_id' => auth()->id(),
                        'sender_type' => auth()->user()->role,
                        'message' => $data['message'],
                    ]);
                    
                    if (!$this->record->is_read) {
                        $this->record->markAsRead();
                    }
                })
                ->successNotificationTitle('Reply sent'),
            Actions\DeleteAction::make()
                ->successNotificationTitle('Message deleted'),
        ];
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Mark message as read when viewed
        if (!$this->record->is_read) {
            $this->record->markAsRead();
        }
        
        return $data;
    }
}
