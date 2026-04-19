<?php

declare(strict_types=1);

namespace App\Domain\Audit\Policies;

use App\Domain\Audit\Models\AuditEntry;
use App\Domain\Identity\Models\User;

class AuditEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.viewAny');
    }

    public function view(User $user, AuditEntry $entry): bool
    {
        return $user->can('audit.view');
    }
}
