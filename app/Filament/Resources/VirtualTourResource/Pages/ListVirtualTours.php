<?php

namespace App\Filament\Resources\VirtualTourResource\Pages;

use App\Filament\Resources\VirtualTourResource;
use App\Models\TourWaypoint;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVirtualTours extends ListRecords
{
    protected static string $resource = VirtualTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open-tour-editor')
                ->label('Open Tour Editor')
                ->icon('heroicon-o-map-pin')
                ->color('success')
                ->disabled(fn (): bool => !$this->getEditorEntryWaypointId())
                ->tooltip('Create a scene first to use the tour editor.')
                ->url(function (): ?string {
                    $waypointId = $this->getEditorEntryWaypointId();

                    return $waypointId
                        ? ManageTourHotspots::getUrl(['record' => $waypointId])
                        : null;
                }),
            Actions\CreateAction::make()->label('New Scene'),
        ];
    }

    protected function getEditorEntryWaypointId(): ?int
    {
        return TourWaypoint::query()
            ->ordered()
            ->value('id');
    }
}
