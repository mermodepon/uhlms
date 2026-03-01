<?php

namespace App\Filament\Resources\StayLogResource\Pages;

use App\Filament\Resources\StayLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStayLogs extends ListRecords
{
    protected static string $resource = StayLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
