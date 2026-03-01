<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\EditRecord;

abstract class EditRedirectToIndex extends EditRecord
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
