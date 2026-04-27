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

        $actionUrl = static::normalizeActionUrl($this->actionUrl);

        if ($actionUrl) {
            $notification->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('View')
                    ->url($actionUrl),
            ]);
        }

        return $notification->getDatabaseMessage();
    }

    public static function normalizeActionUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        // Already relative, so keep it portable across whatever host the app uses.
        if (! isset($parts['host']) && ! isset($parts['scheme'])) {
            return static::remapLegacyPath($url);
        }

        $appHost = parse_url(config('app.url', ''), PHP_URL_HOST);
        $urlHost = $parts['host'] ?? null;

        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) {
            return static::remapLegacyPath($url);
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return static::remapLegacyPath($path.$query.$fragment);
    }

    protected static function remapLegacyPath(string $url): string
    {
        if (str_starts_with($url, '/admin/services')) {
            return preg_replace('#^/admin/services#', '/admin/add-ons', $url, 1) ?? $url;
        }

        return $url;
    }
}
