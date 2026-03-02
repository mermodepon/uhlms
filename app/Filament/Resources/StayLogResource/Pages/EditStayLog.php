<?php

namespace App\Filament\Resources\StayLogResource\Pages;

use App\Filament\Resources\StayLogResource;
use Filament\Actions;
use App\Filament\Pages\EditRedirectToIndex as EditRecord;

class EditStayLog extends EditRecord
{
    protected static string $resource = StayLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->successNotificationTitle('Stay Log deleted'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
