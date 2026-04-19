<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Grievance\Events\GrievanceSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogGrievanceSubmitted implements ShouldQueue
{
    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(GrievanceSubmitted $event): void
    {
        $this->logger->log(
            action: 'grievance.submitted',
            subject: $event->grievance,
            payload: [
                'g_number' => $event->grievance->g_number,
                'is_anonymous' => $event->grievance->is_anonymous,
            ],
        );
    }
}
