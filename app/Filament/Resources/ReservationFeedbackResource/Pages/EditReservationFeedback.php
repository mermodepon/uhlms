<?php

namespace App\Filament\Resources\ReservationFeedbackResource\Pages;

use App\Filament\Resources\ReservationFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReservationFeedback extends EditRecord
{
    protected static string $resource = ReservationFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === 'reviewed' && blank($this->record->reviewed_at)) {
            $data['reviewed_by'] = auth()->id();
            $data['reviewed_at'] = now();
        }

        return $data;
    }
}
