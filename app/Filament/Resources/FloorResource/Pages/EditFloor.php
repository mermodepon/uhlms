<?php

namespace App\Filament\Resources\FloorResource\Pages;

use App\Filament\Pages\EditRedirectToIndex as EditRecord;
use App\Filament\Resources\FloorResource;
use Filament\Actions;

class EditFloor extends EditRecord
{
    protected static string $resource = FloorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->successNotificationTitle('Floor deleted')
                ->disabled(fn ($record) => $record->rooms()->exists())
                ->tooltip(fn ($record) => $record->rooms()->exists()
                        ? 'This floor cannot be deleted because it is linked to rooms.'
                        : null
                ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
