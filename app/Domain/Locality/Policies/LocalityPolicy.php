<?php

declare(strict_types=1);

namespace App\Domain\Locality\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Locality\Models\Locality;

class LocalityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('locality.viewAny');
    }

    public function view(User $user, Locality $locality): bool
    {
        return $user->can('locality.view');
    }

    public function create(User $user): bool
    {
        return $user->can('locality.create');
    }

    public function update(User $user, Locality $locality): bool
    {
        return $user->can('locality.update');
    }

    public function delete(User $user, Locality $locality): bool
    {
        return $user->can('locality.delete');
    }
}
