<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\UserReactivated;
use App\Domain\Identity\Models\User;

class ReactivateUser
{
    public function __invoke(User $user, User $actor): User
    {
        $user->update(['is_active' => true]);

        UserReactivated::dispatch($user, $actor);

        return $user->refresh();
    }
}
