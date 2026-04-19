<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\UserRoleAssigned;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates a user with a random password and dispatches a password-reset
 * email so they set their own. Admins never set passwords directly — that
 * avoids a class of trust-and-trail problems (admin knew the password).
 *
 * Default role is `organization-officer` when no roles provided — matches
 * the "all employees are system users" rule. Callers wanting a different
 * role (grm-officer / super-admin / etc.) must pass it explicitly.
 */
class InviteUser
{
    /**
     * @param  array{
     *   username: string, name: string, email: string,
     *   phone_number?: ?string, position?: ?string,
     *   organization_id?: ?int, office_id?: ?int,
     *   roles?: list<string>,
     * }  $data
     */
    public function __invoke(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $roles = $data['roles'] ?? ['organization-officer'];
            unset($data['roles']);

            $user = User::create([
                ...$data,
                'password' => Str::random(48), // will be replaced on reset
            ]);

            $user->syncRoles($roles);
            UserRoleAssigned::dispatch($user, $roles, $actor);

            Password::sendResetLink(['email' => $user->email]);

            return $user->fresh('roles');
        });
    }
}
