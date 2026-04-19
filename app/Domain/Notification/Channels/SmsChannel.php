<?php

declare(strict_types=1);

namespace App\Domain\Notification\Channels;

use App\Domain\Notification\Contracts\SmsGateway;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway)
    {
    }

    /**
     * Expects the notification to implement `toSms($notifiable): ?SmsMessage`.
     * Returning null skips — respects preferences.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        /** @var SmsMessage|null $message */
        $message = $notification->toSms($notifiable);

        if ($message === null) {
            return;
        }

        $to = $message->to ?? $notifiable->routeNotificationFor('sms', $notification);

        if (! is_string($to) || $to === '') {
            return;
        }

        $this->gateway->send($to, $message->body);
    }
}
