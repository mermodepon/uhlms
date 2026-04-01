<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Pages\CreateRedirectToIndex as CreateRecord;
use App\Filament\Resources\ReservationResource;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
