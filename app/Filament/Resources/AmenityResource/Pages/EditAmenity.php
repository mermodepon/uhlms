<?php

namespace App\Filament\Resources\AmenityResource\Pages;

use App\Filament\Pages\EditRedirectToIndex as EditRecord;
use App\Filament\Resources\AmenityResource;
use Filament\Actions;

class EditAmenity extends EditRecord
{
    protected static string $resource = AmenityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->successNotificationTitle('Amenity deleted'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
