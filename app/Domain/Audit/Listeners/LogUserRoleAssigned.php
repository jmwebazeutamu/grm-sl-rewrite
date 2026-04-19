<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Events\UserRoleAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogUserRoleAssigned implements ShouldQueue
{
    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(UserRoleAssigned $event): void
    {
        $this->logger->log(
            action: 'user.roles_assigned',
            subject: $event->user,
            actor: $event->actor,
            payload: ['roles' => $event->roles],
        );
    }
}
