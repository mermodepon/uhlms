<?php

namespace App\Filament\Resources\VirtualTourResource\Pages;

use App\Filament\Resources\VirtualTourResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVirtualTour extends EditRecord
{
    protected static string $resource = VirtualTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('manage-hotspots')
                ->label('Open Tour Editor')
                ->icon('heroicon-o-map-pin')
                ->url(fn ($record) => ManageTourHotspots::getUrl(['record' => $this->record->id])),
        ];
    }
}
