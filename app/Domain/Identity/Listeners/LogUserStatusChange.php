<?php

declare(strict_types=1);

namespace App\Domain\Identity\Listeners;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Events\UserDeactivated;
use App\Domain\Identity\Events\UserReactivated;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogUserStatusChange implements ShouldQueue
{
    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(UserDeactivated|UserReactivated $event): void
    {
        $action = $event instanceof UserDeactivated ? 'user.deactivated' : 'user.reactivated';

        $this->logger->log(
            action: $action,
            subject: $event->user,
            actor: $event->actor,
            payload: ['username' => $event->user->username],
        );
    }
}
