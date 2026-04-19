<?php

declare(strict_types=1);

namespace App\Domain\Organization\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Programme;

/**
 * Programmes are the "projects" list surfaced on the public grievance form.
 * Super-admin handled by Gate::before. Org-admin manages their own org's
 * projects.
 */
class ProgrammePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['org-admin', 'grm-officer', 'super-admin'])
            || $user->can('programme.viewAny');
    }

    public function view(User $user, Programme $programme): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasAnyRole(['org-admin', 'grm-officer'])) {
            return $this->sameOrg($user, $programme);
        }

        return $user->can('programme.view');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['org-admin', 'super-admin']);
    }

    public function update(User $user, Programme $programme): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasRole('org-admin') && $this->sameOrg($user, $programme);
    }

    public function delete(User $user, Programme $programme): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasRole('org-admin') && $this->sameOrg($user, $programme);
    }

    private function sameOrg(User $user, Programme $programme): bool
    {
        if ($user->organization_id === null) {
            return true;
        }

        return $user->organization_id === $programme->organization_id;
    }
}
