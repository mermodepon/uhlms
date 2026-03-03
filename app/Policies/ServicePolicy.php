<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool                              { return $user->hasPermission('services_view'); }
    public function view(User $user, Service $service): bool               { return $user->hasPermission('services_view'); }
    public function create(User $user): bool                               { return $user->hasPermission('services_create'); }
    public function update(User $user, Service $service): bool             { return $user->hasPermission('services_edit'); }
    public function delete(User $user, Service $service): bool             { return $user->hasPermission('services_delete'); }
    public function deleteAny(User $user): bool                            { return $user->hasPermission('services_delete'); }
    public function restore(User $user, Service $service): bool            { return $user->hasPermission('services_delete'); }
    public function forceDelete(User $user, Service $service): bool        { return $user->hasPermission('services_delete'); }
}
