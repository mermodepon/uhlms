<?php

namespace App\Filament\Resources\RoomTypeResource\Pages;

use App\Filament\Resources\RoomTypeResource;
use App\Filament\Pages\CreateRedirectToIndex as CreateRecord;

class CreateRoomType extends CreateRecord
{
    protected static string $resource = RoomTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
