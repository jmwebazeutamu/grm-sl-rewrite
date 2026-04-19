<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\UserRoleAssigned;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Syncs a user's roles and fires UserRoleAssigned. Callers (controller,
 * policy) are responsible for gating WHICH roles may be assigned — this
 * action trusts its input.
 */
class UpdateUserRole
{
    /**
     * @param  list<string>  $roles
     */
    public function __invoke(User $user, array $roles, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $roles, $actor): User {
            $user->syncRoles($roles);
            UserRoleAssigned::dispatch($user, $roles, $actor);

            return $user->fresh('roles');
        });
    }
}
