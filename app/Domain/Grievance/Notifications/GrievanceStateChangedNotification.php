<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Notifications;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Notification\Channels\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the complainant on significant state changes. Resolved is
 * handled separately (FeedbackInvitationNotification carries the feedback
 * link) so this class filters it out to avoid double-messaging.
 */
class GrievanceStateChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Grievance $grievance,
        public readonly GrievanceState $to,
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
        $statusUrl = route('grievances.public.status', $this->grievance->g_number);

        return (new MailMessage)
            ->subject("Update on grievance {$this->grievance->g_number}")
            ->greeting('Update on your grievance')
            ->line("Your grievance {$this->grievance->g_number} is now **{$this->to->label()}**.")
            ->line($this->bodyFor($this->to))
            ->action('View status', $statusUrl);
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return new SmsMessage(
            body: "GRM-SL: Your grievance {$this->grievance->g_number} is now "
                .strtolower($this->to->label()).'. '.$this->smsDetail($this->to),
        );
    }

    private function bodyFor(GrievanceState $state): string
    {
        return match ($state) {
            GrievanceState::UnderReview => 'Our team is reviewing what you reported.',
            GrievanceState::InProgress => 'An officer is now working on your case.',
            GrievanceState::Rejected => 'After review, we were not able to accept this case. If you believe this is an error, please submit again with additional detail.',
            GrievanceState::Escalated => 'We are re-working your case based on the feedback you provided.',
            default => 'You will receive further updates as the case progresses.',
        };
    }

    private function smsDetail(GrievanceState $state): string
    {
        return match ($state) {
            GrievanceState::UnderReview => 'We will update you as the review progresses.',
            GrievanceState::InProgress => 'An officer is working on it.',
            GrievanceState::Rejected => 'Reason on the case page.',
            GrievanceState::Escalated => 'We are re-working it.',
            default => '',
        };
    }
}
