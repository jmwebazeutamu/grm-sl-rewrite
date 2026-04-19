<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobilePushController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
        ]);

        $user = $request->user();
        $user->forceFill(['expo_push_token' => $data['token']])->save();

        return response()->json(['ok' => true]);
    }

    public function unregister(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['expo_push_token' => null])->save();

        return response()->json(['ok' => true]);
    }
}
