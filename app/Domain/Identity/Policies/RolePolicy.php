<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('role.viewAny');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('role.view');
    }

    public function create(User $user): bool
    {
        return $user->can('role.create');
    }

    public function update(User $user, Role $role): bool
    {
        if (! $user->can('role.update')) {
            return false;
        }

        // super-admin role is sacred — its permission set is controlled
        // by the seeder, not the UI.
        return $role->name !== 'super-admin';
    }

    public function delete(User $user, Role $role): bool
    {
        if (in_array($role->name, ['super-admin', 'complainant'], true)) {
            return false;
        }

        return $user->can('role.delete');
    }
}
