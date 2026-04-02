<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', auth()->id());
    }

    protected function getHeaderActions(): array
    {
        return [
            // Action to clear all read notifications for the current user
            \Filament\Actions\Action::make('clearRead')
                ->label('Clear Read Notifications')
                ->color('warning')
                ->action(function () {
                    \App\Models\Notification::where('notifiable_type', \App\Models\User::class)
                        ->where('notifiable_id', auth()->id())
                        ->where('is_read', true)
                        ->delete();
                    $this->redirect($this->previousUrl ?? $this->getResource()::getUrl('index'));
                })
                ->requiresConfirmation(),
        ];
    }
}
