<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::pages/auth/edit-profile.form.email.label'))
            ->email()
            ->disabled()
            ->dehydrated(false)
            ->helperText('Contact a Super Administrator to change your login email.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['email']);

        return $data;
    }
}
