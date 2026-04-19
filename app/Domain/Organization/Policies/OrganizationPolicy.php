<?php

declare(strict_types=1);

namespace App\Domain\Organization\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;

/**
 * Super-admin bypass happens in AuthServiceProvider::boot via Gate::before —
 * super-admins get `true` back before any method here runs.
 *
 * Below the super-admin layer:
 *  - `organization.viewAny/view/create/update/delete` is the permission set.
 *  - For users whose role is just `organization-admin`, we additionally scope
 *    write-access to their own org. They can read other orgs if their role
 *    has `organization.view`, but they cannot edit anyone else's.
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('organization.viewAny');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can('organization.view');
    }

    public function create(User $user): bool
    {
        return $user->can('organization.create');
    }

    public function update(User $user, Organization $organization): bool
    {
        if (! $user->can('organization.update')) {
            return false;
        }

        // New org-admin role: read-only for the organization entity itself.
        if ($user->hasRole('org-admin')) {
            return false;
        }

        // Legacy organization-admin role: scoped to own org.
        if ($user->hasRole('organization-admin')) {
            return $user->organization_id === $organization->id;
        }

        return true;
    }

    public function delete(User $user, Organization $organization): bool
    {
        // Deletion is super-admin only. Gate::before covers them; for
        // everyone else this is always false — we deliberately don't let an
        // org-admin delete their own org.
        return false;
    }

    /**
     * True when:
     *   - the user is NOT an org-admin (they're some broader role, trust
     *     the permission), OR
     *   - the user IS an org-admin and the target org matches theirs.
     */
    /** Deprecated — kept only so old calls don't break. Prefer update() body. */
    private function belongsToActor(User $user, int $orgId): bool
    {
        return true;
    }
}
