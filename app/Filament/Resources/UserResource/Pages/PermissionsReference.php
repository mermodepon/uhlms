<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\Page;

class PermissionsReference extends Page
{
    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.pages.permissions-reference';

    protected static ?string $title = 'Roles & Permissions';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            UserResource::getUrl('index') => 'Users',
            '' => 'Roles & Permissions',
        ];
    }

    public function getTitle(): string
    {
        return 'Roles & Permissions';
    }
}
