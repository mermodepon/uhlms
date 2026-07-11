<?php

namespace App\Filament\Pages\Auth\PasswordReset;

use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function getSentNotification(string $status): ?Notification
    {
        return $this->getGenericNotification();
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->getGenericNotification();
    }

    protected function getGenericNotification(): Notification
    {
        return Notification::make()
            ->title('If an account exists for that email, a reset link has been sent.')
            ->success();
    }
}
