<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use App\Filament\Pages\EditRedirectToIndex as EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
              Actions\DeleteAction::make()
                 ->disabled(fn ($record) => $record->roomAssignments()->exists() || $record->stayLogs()->exists())
                 ->tooltip('Cannot delete: usage history exists'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
