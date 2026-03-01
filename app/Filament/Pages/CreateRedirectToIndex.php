<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\CreateRecord;

abstract class CreateRedirectToIndex extends CreateRecord
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
