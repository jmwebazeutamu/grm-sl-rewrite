<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Notifications;

use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal notification — GRM officer gets an in-app + email alert when a
 * closed-out case is reopened after the complainant was dissatisfied.
 */
class GrievanceReopenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Grievance $grievance,
        public readonly User $reviewer,
        public readonly string $closureComment,
    ) {
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
            ->subject("Reopened: {$this->grievance->g_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Grievance **{$this->grievance->g_number}** has been reopened after complainant feedback.")
            ->line("Reviewer: {$this->reviewer->name}")
            ->line("Notes: {$this->closureComment}")
            ->action('Open case', $url);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'grievance_id' => $this->grievance->id,
            'g_number' => $this->grievance->g_number,
            'summary' => $this->grievance->summary,
            'reviewer' => $this->reviewer->name,
            'closure_comment' => $this->closureComment,
            'url' => route('admin.grievances.show', $this->grievance->id),
        ];
    }
}
