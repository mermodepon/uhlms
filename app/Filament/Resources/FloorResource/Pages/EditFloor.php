<?php

namespace App\Filament\Resources\FloorResource\Pages;

use App\Filament\Resources\FloorResource;
use Filament\Actions;
use App\Filament\Pages\EditRedirectToIndex as EditRecord;

class EditFloor extends EditRecord
{
    protected static string $resource = FloorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
