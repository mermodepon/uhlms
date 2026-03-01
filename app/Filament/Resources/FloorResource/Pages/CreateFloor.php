<?php

namespace App\Filament\Resources\FloorResource\Pages;

use App\Filament\Resources\FloorResource;
use App\Filament\Pages\CreateRedirectToIndex as CreateRecord;

class CreateFloor extends CreateRecord
{
    protected static string $resource = FloorResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
