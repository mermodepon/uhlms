<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Pages\CreateRedirectToIndex as CreateRecord;
use App\Filament\Resources\RoomResource;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
