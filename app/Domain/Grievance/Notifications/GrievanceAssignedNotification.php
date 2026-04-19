<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Notifications;

use App\Domain\Grievance\Models\Grievance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal notification — officer gets an in-app + email alert when they
 * are assigned a case. SMS is NOT a default channel for internal staff;
 * they'll see it when they log in.
 */
class GrievanceAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Grievance $grievance)
    {
    }

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return array_intersect(
            $notifiable->preferredChannels(),
            ['database', 'mail'],
        );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = route('admin.grievances.show', $this->grievance->id);

        return (new MailMessage)
            ->subject("Assigned: {$this->grievance->g_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You've been assigned grievance **{$this->grievance->g_number}**.")
            ->line($this->grievance->summary)
            ->action('Open case', $url);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'grievance_id' => $this->grievance->id,
            'g_number' => $this->grievance->g_number,
            'summary' => $this->grievance->summary,
            'url' => route('admin.grievances.show', $this->grievance->id),
        ];
    }
}
