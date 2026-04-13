<?php

namespace App\Filament\Resources\RoomHoldResource\Pages;

use App\Filament\Resources\RoomHoldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoomHolds extends ListRecords
{
    protected static string $resource = RoomHoldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action — holds are created through approval/check-in flows
        ];
    }
}
