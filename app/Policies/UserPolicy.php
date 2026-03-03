<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('users_view'); }

    public function view(User $user, User $model): bool {
        if (!$user->hasPermission('users_view')) return false;
        if ($user->isSuperAdmin()) return true;
        // Non-super-admin can only view staff accounts (prevents privilege inspection)
        return $model->role === 'staff';
    }

    public function create(User $user): bool { return $user->hasPermission('users_create'); }

    public function update(User $user, User $model): bool {
        // Self-edit requires only the edit permission
        if ($user->id === $model->id) return $user->hasPermission('users_edit');
        if (!$user->hasPermission('users_edit')) return false;
        if ($user->isSuperAdmin()) return true;
        // Non-super-admin can only edit staff accounts (no privilege escalation)
        return $model->role === 'staff';
    }

    public function delete(User $user, User $model): bool {
        if ($user->id === $model->id) return false; // can never delete self
        return $user->hasPermission('users_delete');
    }
    public function deleteAny(User $user): bool                  { return $user->hasPermission('users_delete'); }
    public function restore(User $user, User $model): bool       { return $user->hasPermission('users_delete'); }
    public function forceDelete(User $user, User $model): bool {
        if ($user->id === $model->id) return false;
        return $user->hasPermission('users_delete');
    }
}
