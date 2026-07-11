<?php

namespace App\Filament\Resources\GuestAccountResource\Pages;

use App\Filament\Resources\GuestAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGuestAccounts extends ListRecords
{
    protected static string $resource = GuestAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
