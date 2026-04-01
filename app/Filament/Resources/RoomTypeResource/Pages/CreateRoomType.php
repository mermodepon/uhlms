<?php

namespace App\Filament\Resources\RoomTypeResource\Pages;

use App\Filament\Pages\CreateRedirectToIndex as CreateRecord;
use App\Filament\Resources\RoomTypeResource;
use Filament\Notifications\Notification;

class CreateRoomType extends CreateRecord
{
    protected static string $resource = RoomTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Room Type created')
            ->body("Room Type \"{$this->record->name}\" has been created successfully.");
    }
}
