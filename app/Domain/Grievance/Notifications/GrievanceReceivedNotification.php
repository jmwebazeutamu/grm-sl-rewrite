<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Notifications;

use App\Domain\Grievance\Models\Grievance;
use App\Domain\Notification\Channels\ExpoChannel;
use App\Domain\Notification\Channels\ExpoMessage;
use App\Domain\Notification\Channels\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Acknowledgement to the complainant when their case is filed. Sent to an
 * AnonymousNotifiable — complainer is a row on the case, not a User.
 */
class GrievanceReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Grievance $grievance)
    {
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
        if ($notifiable->routeNotificationFor('expo')) {
            $channels[] = ExpoChannel::class;
        }

        return $channels;
    }

    public function toExpo(mixed $notifiable): ExpoMessage
    {
        return new ExpoMessage(
            title: 'Grievance filed',
            body: "Your grievance {$this->grievance->g_number} has been received.",
            data: ['g_number' => $this->grievance->g_number, 'kind' => 'received'],
        );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $statusUrl = route('grievances.public.status', $this->grievance->g_number);

        return (new MailMessage)
            ->subject("Grievance {$this->grievance->g_number} received")
            ->greeting('Thank you for reaching out.')
            ->line("We've received your grievance. Your reference number is **{$this->grievance->g_number}**.")
            ->line('Please keep this reference number — you can use it to check status at any time.')
            ->action('Check status', $statusUrl)
            ->line("You can also text STATUS {$this->grievance->g_number} to the GRM short code.");
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return new SmsMessage(
            body: "GRM-SL: Your grievance is filed as {$this->grievance->g_number}. "
                ."Keep this number. Text STATUS {$this->grievance->g_number} for updates.",
        );
    }
}
