<?php

namespace App\Filament\Resources\GuestAccountResource\Pages;

use App\Filament\Resources\GuestAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGuestAccount extends EditRecord
{
    protected static string $resource = GuestAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
