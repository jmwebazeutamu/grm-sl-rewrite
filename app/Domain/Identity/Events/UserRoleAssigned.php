<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRoleAssigned
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public readonly User $user,
        public readonly array $roles,
        public readonly ?User $actor = null,
    ) {
    }
}
