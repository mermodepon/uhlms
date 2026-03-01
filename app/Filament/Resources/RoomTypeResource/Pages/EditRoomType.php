<?php

namespace App\Filament\Resources\RoomTypeResource\Pages;

use App\Filament\Resources\RoomTypeResource;
use Filament\Actions;
use App\Filament\Pages\EditRedirectToIndex as EditRecord;

class EditRoomType extends EditRecord
{
    protected static string $resource = RoomTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => $record->rooms()->exists())
                ->tooltip(fn ($record) =>
                    $record->rooms()->exists()
                        ? 'This room type cannot be deleted because it is linked to rooms.'
                        : null
                ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
