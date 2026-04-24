<?php

namespace App\Filament\Resources\VirtualTourResource\Pages;

use App\Filament\Resources\VirtualTourResource;
use App\Filament\Pages\CreateRedirectToIndex as CreateRecord;
use App\Models\TourWaypoint;

class CreateVirtualTour extends CreateRecord
{
    protected static string $resource = VirtualTourResource::class;

    public function createAnother(): void
    {
        parent::createAnother();

        $this->form->fill([
            'type' => 'entrance',
            'position_order' => $this->getNextPositionOrder(),
            'is_active' => true,
            'panorama_image' => null,
            'thumbnail_image' => null,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! isset($data['position_order']) || $data['position_order'] === '') {
            $data['position_order'] = $this->getNextPositionOrder();
        }

        return $data;
    }

    private function getNextPositionOrder(): int
    {
        return ((int) TourWaypoint::max('position_order')) + 1;
    }
}
