<?php

namespace App\Filament\Resources\AmenityResource\Pages;

use App\Filament\Pages\CreateRedirectToIndex as CreateRecord;
use App\Filament\Resources\AmenityResource;

class CreateAmenity extends CreateRecord
{
    protected static string $resource = AmenityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
