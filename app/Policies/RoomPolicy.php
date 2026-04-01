<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('rooms_view');
    }

    public function view(User $user, Room $room): bool
    {
        return $user->hasPermission('rooms_view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('rooms_create');
    }

    public function update(User $user, Room $room): bool
    {
        return $user->hasPermission('rooms_edit');
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->hasPermission('rooms_delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('rooms_delete');
    }

    public function restore(User $user, Room $room): bool
    {
        return $user->hasPermission('rooms_delete');
    }

    public function forceDelete(User $user, Room $room): bool
    {
        return $user->hasPermission('rooms_delete');
    }
}
