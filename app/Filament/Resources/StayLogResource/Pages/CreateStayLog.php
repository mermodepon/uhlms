<?php

namespace App\Filament\Resources\StayLogResource\Pages;

use App\Filament\Resources\StayLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStayLog extends CreateRecord
{
    protected static string $resource = StayLogResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
