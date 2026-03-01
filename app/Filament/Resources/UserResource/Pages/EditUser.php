<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use App\Filament\Pages\EditRedirectToIndex as EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->disabled(fn ($record) => $record->roomAssignments()->exists() || $record->reviewedReservations()->exists())
                ->tooltip(fn ($record) =>
                    ($record->roomAssignments()->exists() || $record->reviewedReservations()->exists())
                        ? 'This user cannot be deleted because they are linked to room assignments or reservations.'
                        : null
                ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
