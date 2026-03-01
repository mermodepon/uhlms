<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use Filament\Resources\Pages\ListRecords;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Action to clear all read notifications
            \Filament\Actions\Action::make('clearRead')
                ->label('Clear Read Notifications')
                ->color('warning')
                ->action(function () {
                    \App\Models\Notification::where('is_read', true)->delete();
                    $this->redirect($this->previousUrl ?? $this->getResource()::getUrl('index'));
                })
                ->requiresConfirmation(),
        ];
    }
}
