<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\UserDeactivated;
use App\Domain\Identity\Models\User;

class DeactivateUser
{
    public function __invoke(User $user, User $actor): User
    {
        $user->update(['is_active' => false]);

        UserDeactivated::dispatch($user, $actor);

        return $user->refresh();
    }
}
