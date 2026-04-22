<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Listeners;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Notifications\GrievanceStateChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

class SendStateChangeNotification implements ShouldQueue
{
    /** @var list<GrievanceState> */
    private const SILENT = [
        // Resolved is covered by FeedbackRequested -> SendFeedbackInvitation,
        // which delivers the rating/feedback link; skipping here avoids a
        // double email. Closed gets the generic complainant status update.
        GrievanceState::Resolved,
        GrievanceState::Trashed,
    ];

    public function handle(GrievanceStateChanged $event): void
    {
        if (in_array($event->to, self::SILENT, true)) {
            return;
        }

        $grievance = $event->grievance->loadMissing('complainer');

        if ($grievance->is_anonymous || $grievance->complainer === null) {
            return;
        }

        $notifiable = new AnonymousNotifiable;
        $notified = false;
        if ($grievance->complainer->email) {
            $notifiable->route('mail', $grievance->complainer->email);
            $notified = true;
        }
        if ($grievance->complainer->phone_number) {
            $notifiable->route('sms', $grievance->complainer->phone_number);
            $notified = true;
        }

        if (! $notified) {
            return;
        }

        $notifiable->notify(
            new GrievanceStateChangedNotification($grievance, $event->to),
        );
    }
}
