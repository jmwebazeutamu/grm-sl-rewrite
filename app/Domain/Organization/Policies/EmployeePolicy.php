<?php

declare(strict_types=1);

namespace App\Domain\Organization\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Employee;

/**
 * Org-admins manage employees within their own org only. Super-admin bypass
 * is in AuthServiceProvider via Gate::before.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employee.viewAny');
    }

    public function view(User $user, Employee $employee): bool
    {
        if (! $user->can('employee.view')) {
            return false;
        }

        return $this->inActorOrg($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->can('employee.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employee.update') && $this->inActorOrg($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employee.delete') && $this->inActorOrg($user, $employee);
    }

    private function inActorOrg(User $user, Employee $employee): bool
    {
        if (! $user->hasRole('organization-admin')) {
            return true;
        }

        return $user->organization_id === $employee->organization_id;
    }
}
