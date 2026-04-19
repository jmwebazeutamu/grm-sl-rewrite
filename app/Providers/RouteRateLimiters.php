<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Centralizes named rate limiters. Referenced via `throttle:<name>`
 * middleware in routes/web.php and routes/api.php.
 */
class RouteRateLimiters extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Public grievance submission: generous enough for legitimate traffic,
        // tight enough to blunt scripted abuse. reCAPTCHA is the other gate.
        RateLimiter::for('grievance-submit', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(30)->by($request->ip()),
        ]);

        // Inbound SMS webhook — high enough for real traffic, low enough
        // that a misbehaving provider or leaked secret doesn't cost us a
        // database. Keyed by IP (the provider's).
        RateLimiter::for('sms-inbound', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
