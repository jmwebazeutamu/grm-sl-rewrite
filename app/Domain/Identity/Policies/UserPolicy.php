<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;

/**
 * User admin authorization. Super-admin bypass is in AuthServiceProvider.
 *
 * Abilities:
 *   viewAny, view, create, update (details), assignRoles, grantRole,
 *   resetPassword, deactivate, delete.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.viewAny');
    }

    public function view(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }

        if (! $user->can('user.view')) {
            return false;
        }

        return $this->sameOrg($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $target): bool
    {
        if (! $user->can('user.update')) {
            return false;
        }

        if ($target->hasAnyRole(['super-admin', 'acc-reviewer'])) {
            return false;
        }

        return $this->sameOrg($user, $target);
    }

    public function assignRoles(User $user, User $target): bool
    {
        return $this->update($user, $target) && $user->can('user.assign_roles');
    }

    public function grantRole(User $user, string $roleName): bool
    {
        if (in_array($roleName, ['super-admin', 'acc-reviewer', 'org-admin'], true)) {
            return $user->hasRole('super-admin');
        }

        return $user->can('user.assign_roles');
    }

    /**
     * Admin-triggered password reset (both email and direct modes).
     * Cannot target super-admin or acc-reviewer accounts.
     */
    public function resetPassword(User $user, User $target): bool
    {
        if (! $user->can('user.update')) {
            return false;
        }

        if ($target->hasAnyRole(['super-admin', 'acc-reviewer'])) {
            return false;
        }

        return $this->sameOrg($user, $target);
    }

    /**
     * Deactivate / reactivate. Only org-admin can do this (not grm-officer).
     * Cannot target self, super-admin, or acc-reviewer.
     */
    public function deactivate(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        if (! $user->can('user.update')) {
            return false;
        }

        if (! $user->hasAnyRole(['org-admin'])) {
            return false;
        }

        if ($target->hasAnyRole(['super-admin', 'acc-reviewer'])) {
            return false;
        }

        return $this->sameOrg($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        return $this->update($user, $target) && $user->can('user.delete');
    }

    private function sameOrg(User $user, User $target): bool
    {
        if ($user->organization_id === null) {
            return true;
        }

        return $user->organization_id === $target->organization_id;
    }
}
