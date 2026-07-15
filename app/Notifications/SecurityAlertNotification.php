<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $metadata = [],
        public readonly string $level = 'warning',
    ) {
        $this->onQueue('default');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[UHLMS Security] '.$this->title)
            ->line($this->body);

        foreach ($this->metadata as $key => $value) {
            $mail->line(str_replace('_', ' ', ucfirst($key)).': '.($value ?? 'none'));
        }

        return $mail->line('Review the dedicated security log and the relevant administrative area.');
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body);

        match ($this->level) {
            'danger', 'error' => $notification->danger(),
            'success' => $notification->success(),
            default => $notification->warning(),
        };

        return $notification->getDatabaseMessage();
    }
}
