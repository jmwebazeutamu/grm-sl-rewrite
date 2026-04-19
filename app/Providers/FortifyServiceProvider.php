<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Authenticate by `username` against the legacy `person` table.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('username', $request->input('username'))->first();

            if ($user && Hash::check($request->input('password'), $user->password)) {
                // Block deactivated users at the login gate.
                if ($user->is_active === false) {
                    session()->flash('error', 'Your account has been deactivated. Contact your administrator.');

                    return null;
                }

                return $user;
            }

            return null;
        });

        Fortify::loginView(fn () => inertia('Auth/Login'));
        Fortify::requestPasswordResetLinkView(fn () => inertia('Auth/ForgotPassword'));
        Fortify::resetPasswordView(fn ($request) => inertia('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(fn () => inertia('Auth/VerifyEmail'));
        Fortify::twoFactorChallengeView(fn () => inertia('Auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => inertia('Auth/ConfirmPassword'));

        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::transliterate(
                Str::lower($request->input('username')).'|'.$request->ip(),
            );

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
