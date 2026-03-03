<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool                              { return $user->hasPermission('settings_view'); }
    public function view(User $user, Setting $setting): bool               { return $user->hasPermission('settings_view'); }
    public function create(User $user): bool                               { return $user->hasPermission('settings_edit'); }
    public function update(User $user, Setting $setting): bool             { return $user->hasPermission('settings_edit'); }
    public function delete(User $user, Setting $setting): bool             { return $user->hasPermission('settings_edit'); }
    public function deleteAny(User $user): bool                            { return $user->hasPermission('settings_edit'); }
    public function restore(User $user, Setting $setting): bool            { return $user->hasPermission('settings_edit'); }
    public function forceDelete(User $user, Setting $setting): bool        { return $user->hasPermission('settings_edit'); }
}
