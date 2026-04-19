<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

class ResetUserPasswordDirect
{
    public function __invoke(User $user, string $password): User
    {
        $user->update(['password' => Hash::make($password)]);

        return $user->refresh();
    }
}
