<?php

namespace App\Filament\Resources\RoomTypeResource\Pages;

use App\Filament\Pages\EditRedirectToIndex as EditRecord;
use App\Filament\Resources\RoomTypeResource;
use Filament\Actions;
use Filament\Notifications\Notification;

class EditRoomType extends EditRecord
{
    protected static string $resource = RoomTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->successNotificationTitle('Room Type deleted')
                ->disabled(fn ($record) => $record->rooms()->exists())
                ->tooltip(fn ($record) => $record->rooms()->exists()
                        ? 'This room type cannot be deleted because it is linked to rooms.'
                        : null
                ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Room Type updated')
            ->body("Room Type \"{$this->record->name}\" has been updated successfully.");
    }
}
