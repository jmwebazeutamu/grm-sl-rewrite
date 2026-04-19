<?php

declare(strict_types=1);

namespace App\Domain\Organization\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Office;

class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('office.viewAny');
    }

    public function view(User $user, Office $office): bool
    {
        return $user->can('office.view');
    }

    public function create(User $user): bool
    {
        return $user->can('office.create');
    }

    public function update(User $user, Office $office): bool
    {
        return $user->can('office.update');
    }

    public function delete(User $user, Office $office): bool
    {
        return $user->can('office.delete');
    }
}
