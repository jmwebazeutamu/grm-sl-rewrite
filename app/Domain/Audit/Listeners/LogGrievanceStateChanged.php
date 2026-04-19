<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogGrievanceStateChanged implements ShouldQueue
{
    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(GrievanceStateChanged $event): void
    {
        $this->logger->log(
            action: 'grievance.state_changed',
            subject: $event->grievance,
            payload: [
                'g_number' => $event->grievance->g_number,
                'from' => $event->from?->value,
                'to' => $event->to->value,
                'note' => $event->note,
            ],
        );
    }
}
