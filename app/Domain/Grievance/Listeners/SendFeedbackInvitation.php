<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Listeners;

use App\Domain\Grievance\Events\FeedbackRequested;
use App\Domain\Grievance\Notifications\FeedbackInvitationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

class SendFeedbackInvitation implements ShouldQueue
{
    public function handle(FeedbackRequested $event): void
    {
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
            new FeedbackInvitationNotification($grievance, $event->token),
        );
    }
}
