<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => [
                'user' => $request->user()?->only('id', 'username', 'email', 'name'),
                'roles' => fn () => $request->user()?->roles->pluck('name') ?? [],
                'permissions' => fn () => $request->user()?->getAllPermissions()->pluck('name') ?? [],
                'organization_id' => $request->user()?->organization_id,
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
            ],
        ];
    }
}
