<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('addons_view');
    }

    public function view(User $user, Service $service): bool
    {
        return $user->hasPermission('addons_view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('addons_create');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->hasPermission('addons_edit');
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->hasPermission('addons_delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('addons_delete');
    }

    public function restore(User $user, Service $service): bool
    {
        return $user->hasPermission('addons_delete');
    }

    public function forceDelete(User $user, Service $service): bool
    {
        return $user->hasPermission('addons_delete');
    }
}
