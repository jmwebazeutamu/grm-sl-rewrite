<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use DomainException;
use Illuminate\Support\Facades\Password;

class ResetUserPasswordByEmail
{
    public function __invoke(User $user): void
    {
        if (empty($user->email)) {
            throw new DomainException('This user has no email address. Use the direct password method instead.');
        }

        Password::sendResetLink(['email' => $user->email]);
    }
}
