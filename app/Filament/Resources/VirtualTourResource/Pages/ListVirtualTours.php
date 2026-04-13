<?php

namespace App\Filament\Resources\VirtualTourResource\Pages;

use App\Filament\Resources\VirtualTourResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVirtualTours extends ListRecords
{
    protected static string $resource = VirtualTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New Waypoint'),
        ];
    }
}
