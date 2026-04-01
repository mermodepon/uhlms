<?php

namespace App\Policies;

use App\Models\RoomType;
use App\Models\User;

class RoomTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('room_types_view');
    }

    public function view(User $user, RoomType $roomType): bool
    {
        return $user->hasPermission('room_types_view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('room_types_create');
    }

    public function update(User $user, RoomType $roomType): bool
    {
        return $user->hasPermission('room_types_edit');
    }

    public function delete(User $user, RoomType $roomType): bool
    {
        return $user->hasPermission('room_types_delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('room_types_delete');
    }

    public function restore(User $user, RoomType $roomType): bool
    {
        return $user->hasPermission('room_types_delete');
    }

    public function forceDelete(User $user, RoomType $roomType): bool
    {
        return $user->hasPermission('room_types_delete');
    }
}
