<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Notifications;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Notification\Channels\ExpoChannel;
use App\Domain\Notification\Channels\ExpoMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal notification fired when a grievance is resolved or closed.
 * Delivered to the assigned officer and the owning-org admin(s).
 */
class GrievanceStaffStateChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Grievance $grievance,
        public readonly GrievanceState $to,
        public readonly ?string $note = null,
    ) {
    }

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        $channels = array_values(array_intersect(
            $notifiable->preferredChannels(),
            ['database', 'mail'],
        ));

        if ($notifiable->routeNotificationFor('expo')) {
            $channels[] = ExpoChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $verb = $this->to === GrievanceState::Closed ? 'Closed' : 'Resolved';
        $url = route('admin.grievances.show', $this->grievance->id);

        $message = (new MailMessage)
            ->subject("{$verb}: {$this->grievance->g_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Grievance **{$this->grievance->g_number}** is now **{$this->to->label()}**.")
            ->line($this->grievance->summary);

        if ($this->note) {
            $message->line("Note: {$this->note}");
        }

        return $message->action('Open case', $url);
    }

    public function toExpo(mixed $notifiable): ExpoMessage
    {
        return new ExpoMessage(
            title: $this->grievance->g_number,
            body: "Status: {$this->to->label()}",
            data: [
                'grievance_id' => $this->grievance->id,
                'g_number' => $this->grievance->g_number,
                'state' => $this->to->value,
                'kind' => 'staff_state_change',
            ],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'grievance_id' => $this->grievance->id,
            'g_number' => $this->grievance->g_number,
            'state' => $this->to->value,
            'note' => $this->note,
            'url' => route('admin.grievances.show', $this->grievance->id),
        ];
    }
}
