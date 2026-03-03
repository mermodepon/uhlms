<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PermissionsReference extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static ?string $title = 'Roles & Permissions Reference';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.permissions-reference';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        // Admins always see this; staff see it if they have any settings permission so they can understand their own access
        return $user->isAdmin() || $user->hasPermission('settings_view') || $user->hasPermission('settings_edit');
    }
}
