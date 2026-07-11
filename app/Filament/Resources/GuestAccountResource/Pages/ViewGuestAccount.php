<?php

namespace App\Filament\Resources\GuestAccountResource\Pages;

use App\Filament\Resources\GuestAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGuestAccount extends ViewRecord
{
    protected static string $resource = GuestAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
