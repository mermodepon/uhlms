<?php

namespace App\Policies;

use App\Models\Amenity;
use App\Models\User;

class AmenityPolicy
{
    public function viewAny(User $user): bool                              { return $user->hasPermission('amenities_view'); }
    public function view(User $user, Amenity $amenity): bool               { return $user->hasPermission('amenities_view'); }
    public function create(User $user): bool                               { return $user->hasPermission('amenities_create'); }
    public function update(User $user, Amenity $amenity): bool             { return $user->hasPermission('amenities_edit'); }
    public function delete(User $user, Amenity $amenity): bool             { return $user->hasPermission('amenities_delete'); }
    public function deleteAny(User $user): bool                            { return $user->hasPermission('amenities_delete'); }
    public function restore(User $user, Amenity $amenity): bool            { return $user->hasPermission('amenities_delete'); }
    public function forceDelete(User $user, Amenity $amenity): bool        { return $user->hasPermission('amenities_delete'); }
}
