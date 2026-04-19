<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Listeners;

use App\Domain\Grievance\Events\GrievanceReopened;
use App\Domain\Grievance\Notifications\GrievanceReopenedNotification;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyOrgOnReopen implements ShouldQueue
{
    public function handle(GrievanceReopened $event): void
    {
        $orgId = $event->grievance->classified_organization_id;
        if ($orgId === null) {
            return;
        }

        $officers = User::where('organization_id', $orgId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'grm-officer'))
            ->get();

        if ($officers->isEmpty()) {
            return;
        }

        Notification::send(
            $officers,
            new GrievanceReopenedNotification($event->grievance, $event->reviewer, $event->closureComment),
        );
    }
}
