<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from deactivated users. Catches both:
 * - Deactivated users trying to login (via Fortify's authenticateUsing)
 * - Users deactivated WHILE logged in (existing session)
 *
 * Applied to the web middleware stack after auth.
 */
class CheckUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->getAttribute('is_active') === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account has been deactivated. Contact your administrator.');
        }

        return $next($request);
    }
}
