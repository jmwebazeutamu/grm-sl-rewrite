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
                // Explicit guard against future DB default changes; also lines
                // up with the `! $user->is_active` check in the login gate.
                'is_active' => true,
                // email_verified_at stays null here. We want the user to click
                // the invite link (proof of inbox possession); ResetUserPassword
                // sets email_verified_at on successful password-set, so they
                // don't get bounced to /email/verify after login.
            ]);

            $user->syncRoles($roles);
            UserRoleAssigned::dispatch($user, $roles, $actor);

            Password::sendResetLink(['email' => $user->email]);

            return $user->fresh('roles');
        });
    }
}
