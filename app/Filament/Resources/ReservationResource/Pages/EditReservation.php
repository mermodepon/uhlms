<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * The generic editor is deliberately restricted to a pending request draft.
 * All lifecycle, pre-stay, in-stay and financial changes use dedicated actions.
 */
class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->status !== 'pending') {
            Notification::make()
                ->title('Use the reservation workflow actions')
                ->body('Only pending reservation drafts can be edited here. This record is protected from generic changes.')
                ->warning()
                ->send();

            $this->redirect(ReservationResource::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Status is never a form-editable field, including forged requests.
        unset($data['status']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
