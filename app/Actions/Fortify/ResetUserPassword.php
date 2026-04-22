<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * Validate and reset the user's forgotten password.
     *
     * Also verifies the email on first password-set. Clicking the signed
     * reset link already proves the user owns the inbox, so requiring a
     * separate /email/verify click after login is redundant and silently
     * traps newly-invited users in a redirect loop.
     *
     * @param  mixed  $user
     * @param  array<string, string>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ])->validate();

        $attrs = ['password' => Hash::make($input['password'])];

        $justVerified = false;
        if ($user->email_verified_at === null) {
            $attrs['email_verified_at'] = now();
            $justVerified = true;
        }

        $user->forceFill($attrs)->save();

        // Fire the Verified event so downstream listeners (audit, welcome
        // email, etc.) run exactly as they would on the normal verify flow.
        if ($justVerified) {
            event(new Verified($user));
        }
    }
}
