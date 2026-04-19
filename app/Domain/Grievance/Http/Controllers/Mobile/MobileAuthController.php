<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Mobile;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['username' => ['Invalid credentials.']]);
        }

        if ($user->is_active === false) {
            throw ValidationException::withMessages(['username' => ['Account is deactivated.']]);
        }

        $token = $user->createToken('mobile-'.$request->header('X-Mobile-App', 'expo'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['organization:id,name,acronym', 'roles:id,name']);

        return response()->json(['user' => $this->userPayload($user)]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['organization:id,name,acronym', 'roles:id,name']);

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'position' => $user->position,
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'acronym' => $user->organization->getAttribute('acronym'),
            ] : null,
            'roles' => $user->roles->pluck('name')->values(),
            'is_active' => $user->is_active,
        ];
    }
}
