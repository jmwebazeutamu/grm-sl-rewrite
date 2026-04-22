<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Bootstraps the `admin` super-admin account every environment needs.
 *
 * Idempotent: re-running matches on username, updates name/email, leaves
 * an existing password intact so a production admin can change theirs
 * without a re-seed wiping it.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew(['username' => 'admin']);
        $wasNew = ! $user->exists;

        $user->fill([
            'name' => 'Super Admin',
            'email' => 'admin@grm-sl.local',
            'is_active' => true,
            // Pre-verified so Fortify doesn't redirect to the email-verify page
            // on first login (the VerifyEmail Vue page isn't shipped yet).
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        // Only set a password when bootstrapping — don't overwrite a password
        // an admin may have already changed in production.
        if ($wasNew) {
            $user->password = Hash::make('ChangeMe123!');
        }

        $user->save();
        $user->syncRoles(['super-admin']);
    }
}
