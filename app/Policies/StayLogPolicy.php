<?php

namespace App\Policies;

use App\Models\StayLog;
use App\Models\User;

class StayLogPolicy
{
    public function viewAny(User $user): bool                              { return $user->hasPermission('stay_logs_view'); }
    public function view(User $user, StayLog $stayLog): bool               { return $user->hasPermission('stay_logs_view'); }
    public function create(User $user): bool                               { return $user->hasPermission('stay_logs_view'); }
    public function update(User $user, StayLog $stayLog): bool             { return $user->hasPermission('stay_logs_view'); }
    public function delete(User $user, StayLog $stayLog): bool             { return $user->hasPermission('stay_logs_view'); }
    public function deleteAny(User $user): bool                            { return $user->hasPermission('stay_logs_view'); }
    public function restore(User $user, StayLog $stayLog): bool            { return $user->hasPermission('stay_logs_view'); }
    public function forceDelete(User $user, StayLog $stayLog): bool        { return $user->hasPermission('stay_logs_view'); }
}
