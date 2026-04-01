<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reservations_view');
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->hasPermission('reservations_view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('reservations_create');
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->hasPermission('reservations_edit');
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->hasPermission('reservations_delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('reservations_delete');
    }

    public function restore(User $user, Reservation $reservation): bool
    {
        return $user->hasPermission('reservations_delete');
    }

    public function forceDelete(User $user, Reservation $reservation): bool
    {
        return $user->hasPermission('reservations_delete');
    }
}
