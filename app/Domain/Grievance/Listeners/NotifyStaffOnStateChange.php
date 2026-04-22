<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Listeners;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Notifications\GrievanceStaffStateChangeNotification;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Staff-facing state-change notifications. Handles resolved + closed only —
 * other states either don't warrant internal alerts (submitted, under_review)
 * or already have dedicated listeners (reopened, assigned).
 */
class NotifyStaffOnStateChange implements ShouldQueue
{
    /** @var list<GrievanceState> */
    private const STAFF_NOTIFIABLE = [
        GrievanceState::Resolved,
        GrievanceState::Closed,
    ];

    public function handle(GrievanceStateChanged $event): void
    {
        if (! in_array($event->to, self::STAFF_NOTIFIABLE, true)) {
            return;
        }

        $grievance = $event->grievance->loadMissing('assignedOfficer');
        $orgId = $grievance->classified_organization_id;
        if ($orgId === null) {
            return;
        }

        $recipients = collect();

        if ($grievance->assignedOfficer !== null) {
            $recipients->push($grievance->assignedOfficer);
        }

        $admins = User::where('organization_id', $orgId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'org-admin'))
            ->get();

        $recipients = $recipients->merge($admins)->unique('id')->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new GrievanceStaffStateChangeNotification($grievance, $event->to, $event->note),
        );
    }
}
