<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

class UpdateUserDetails
{
    /**
     * @param array{name?: string, email?: ?string, phone_number?: ?string, position?: ?string, office_id?: ?int} $data
     */
    public function __invoke(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }
}
