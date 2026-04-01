<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PermissionsReference extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static ?string $title = 'Roles & Permissions Reference';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.permissions-reference';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->isAdmin();
    }
}
