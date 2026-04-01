<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Pages\EditRedirectToIndex as EditRecord;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->successNotificationTitle('User deleted')
                ->disabled(fn ($record) => $record->roomAssignments()->exists() || $record->reviewedReservations()->exists())
                ->tooltip(fn ($record) => ($record->roomAssignments()->exists() || $record->reviewedReservations()->exists())
                        ? 'This user cannot be deleted because they are linked to room assignments or reservations.'
                        : null
                ),
        ];
    }

    /**
     * Pre-fill the form: inject `use_custom_permissions` and seed permission
     * toggle values from role defaults when no custom permissions are stored.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $hasCustom = isset($data['permissions']) && $data['permissions'] !== null;
        $data['use_custom_permissions'] = $hasCustom;

        // Seed toggles with role defaults so super admin sees a useful starting
        // point before they enable custom permissions for the first time.
        if (! $hasCustom) {
            $data['permissions'] = User::defaultPermissionsForRole($data['role'] ?? 'staff');
        }

        return $data;
    }

    /**
     * Before saving: if custom permissions are disabled, set permissions to null
     * so the role-based defaults kick in. Strip the virtual toggle field.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['use_custom_permissions'])) {
            $data['permissions'] = null;
        }
        unset($data['use_custom_permissions']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
