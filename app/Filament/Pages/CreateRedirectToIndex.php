<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

abstract class CreateRedirectToIndex extends CreateRecord
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $modelName = $this->getModel();
        $entityName = class_basename($modelName);

        return Notification::make()
            ->success()
            ->title($entityName.' created')
            ->body('The '.strtolower($entityName).' has been created successfully.');
    }
}
