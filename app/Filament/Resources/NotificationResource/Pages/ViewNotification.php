<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewNotification extends ViewRecord
{
    protected static string $resource = NotificationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Mark as read when viewing
        $this->record->markAsRead();

        return $data;
    }
}
