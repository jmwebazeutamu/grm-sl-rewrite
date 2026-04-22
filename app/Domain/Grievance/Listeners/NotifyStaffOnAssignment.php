<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Listeners;

use App\Domain\Grievance\Events\GrievanceOfficerAssigned;
use App\Domain\Grievance\Notifications\GrievanceAssignedNotification;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the newly-assigned officer and the org admin(s) of the owning org.
 * The actor is excluded so the person performing the assignment doesn't get
 * a notification about their own action.
 */
class NotifyStaffOnAssignment implements ShouldQueue
{
    public function handle(GrievanceOfficerAssigned $event): void
    {
        $recipients = collect([$event->officer]);

        $orgId = $event->grievance->classified_organization_id;
        if ($orgId !== null) {
            $admins = User::where('organization_id', $orgId)
                ->whereHas('roles', fn ($q) => $q->where('name', 'org-admin'))
                ->get();
            $recipients = $recipients->merge($admins);
        }

        $recipients = $recipients
            ->unique('id')
            ->reject(fn (User $u) => $u->id === $event->actor->id)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new GrievanceAssignedNotification($event->grievance));
    }
}
