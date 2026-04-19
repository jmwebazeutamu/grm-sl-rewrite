<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Notifications;

use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceFeedbackToken;
use App\Domain\Notification\Channels\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a case is Resolved. Carries the one-shot signed feedback URL.
 * Complainant clicks it; no login required. See FeedbackController::show.
 */
class FeedbackInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Grievance $grievance,
        public readonly GrievanceFeedbackToken $token,
    ) {
    }

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        $channels = [];
        if ($notifiable->routeNotificationFor('mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->routeNotificationFor('sms')) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = route('grievances.feedback.show', $this->token->token);

        return (new MailMessage)
            ->subject("Your grievance {$this->grievance->g_number} is resolved — please rate it")
            ->greeting("Your case is resolved.")
            ->line("Reference: **{$this->grievance->g_number}**")
            ->line('Please take a moment to tell us whether the resolution is satisfactory. This link is good for 30 days.')
            ->action('Give feedback', $url)
            ->line('If you are not satisfied, we will re-open the case.');
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        $url = route('grievances.feedback.show', $this->token->token);

        return new SmsMessage(
            body: "GRM-SL: Your grievance {$this->grievance->g_number} is resolved. "
                ."Rate the outcome: {$url}  (link works for 30 days)",
        );
    }
}
