<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Listeners;

use App\Domain\Grievance\Events\GrievanceSubmitted;
use App\Domain\Grievance\Notifications\GrievanceReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

class SendGrievanceReceivedNotification implements ShouldQueue
{
    public function handle(GrievanceSubmitted $event): void
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

        $notifiable->notify(new GrievanceReceivedNotification($grievance));
    }
}
