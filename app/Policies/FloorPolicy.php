<?php

namespace App\Policies;

use App\Models\Floor;
use App\Models\User;

class FloorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('floors_view');
    }

    public function view(User $user, Floor $floor): bool
    {
        return $user->hasPermission('floors_view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('floors_create');
    }

    public function update(User $user, Floor $floor): bool
    {
        return $user->hasPermission('floors_edit');
    }

    public function delete(User $user, Floor $floor): bool
    {
        return $user->hasPermission('floors_delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('floors_delete');
    }

    public function restore(User $user, Floor $floor): bool
    {
        return $user->hasPermission('floors_delete');
    }

    public function forceDelete(User $user, Floor $floor): bool
    {
        return $user->hasPermission('floors_delete');
    }
}
