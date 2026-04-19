<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\UserRoleAssigned;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Direct user creation. Admin sets the password; user is immediately usable.
 * No email sent, no reset link. Mirror of InviteUser minus the email step.
 *
 * Password is hashed here via Hash::make — never rely on callers to hash.
 * Default role is `organization-officer` when no roles provided, matching
 * InviteUser for consistency.
 */
class CreateUser
{
    /**
     * @param  array{
     *   username: string, name: string, password: string,
     *   email?: ?string, phone_number?: ?string, position?: ?string,
     *   organization_id?: ?int, office_id?: ?int,
     *   roles?: list<string>,
     * }  $data
     */
    public function __invoke(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $roles = $data['roles'] ?? ['organization-officer'];
            unset($data['roles']);

            // Hash here, always. Callers pass the plain password; we never
            // accept a pre-hashed value through this action.
            $data['password'] = Hash::make($data['password']);

            $user = User::create($data);

            // Direct-created users are considered verified — admin vouched
            // for them by creating the account with a password. This also
            // lets them hit `verified`-gated routes immediately.
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->syncRoles($roles);
            UserRoleAssigned::dispatch($user, $roles, $actor);

            return $user->fresh('roles');
        });
    }
}
