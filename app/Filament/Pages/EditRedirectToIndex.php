<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

abstract class EditRedirectToIndex extends EditRecord
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        $modelName = $this->getModel();
        $entityName = class_basename($modelName);

        return Notification::make()
            ->success()
            ->title($entityName.' updated')
            ->body('The '.strtolower($entityName).' has been updated successfully.');
    }
}
