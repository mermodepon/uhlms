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
            'linked_room_type_id' => null,
            'linked_room_id' => null,
        ]);

        $this->js('window.scrollTo({ top: 0, behavior: "smooth" })');

        // FilePond uses wire:ignore so Livewire state changes don't clear its JS instance.
        // Manually remove files from all FilePond instances on the page.
        $this->js('
            setTimeout(function () {
                document.querySelectorAll(".fi-fo-file-upload").forEach(function (el) {
                    if (el._x_dataStack && el._x_dataStack[0] && el._x_dataStack[0].pond) {
                        el._x_dataStack[0].pond.removeFiles();
                    }
                });
            }, 100);
        ');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! isset($data['position_order']) || $data['position_order'] === '') {
            $data['position_order'] = $this->getNextPositionOrder();
        }

        $data['linked_room_id'] = null;

        return $data;
    }

    private function getNextPositionOrder(): int
    {
        return ((int) TourWaypoint::max('position_order')) + 1;
    }
}
