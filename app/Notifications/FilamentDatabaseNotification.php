<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class FilamentDatabaseNotification extends Notification
{

    public function __construct(
        protected string $title,
        protected string $body,
        protected string $type = 'info',
        protected ?string $actionUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body);

        // Map custom type to Filament notification status
        match ($this->type) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger', 'error' => $notification->danger(),
            default => $notification->info(),
        };

        if ($this->actionUrl) {
            $notification->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('View')
                    ->url($this->actionUrl),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
