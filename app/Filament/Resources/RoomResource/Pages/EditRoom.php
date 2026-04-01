<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Pages\EditRedirectToIndex as EditRecord;
use App\Filament\Resources\RoomResource;
use Filament\Actions;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->successNotificationTitle('Room deleted')
                ->disabled(fn ($record) => $record->roomAssignments()->exists())
                ->tooltip('Cannot delete: usage history exists'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
