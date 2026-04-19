<?php

declare(strict_types=1);

namespace App\Domain\Notification\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers push notifications via Expo's push-send service. The client
 * registers its ExponentPushToken[xxx] via MobilePushController which
 * stores it on person.expo_push_token; this channel reads that token
 * through the notifiable's routeNotificationForExpo().
 */
class ExpoChannel
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toExpo')) {
            return;
        }

        $token = $notifiable->routeNotificationFor('expo', $notification);
        if (! is_string($token) || $token === '') {
            return;
        }

        /** @var ExpoMessage $message */
        $message = $notification->toExpo($notifiable);

        try {
            Http::timeout(5)->post(self::ENDPOINT, [
                'to' => $token,
                'title' => $message->title,
                'body' => $message->body,
                'data' => $message->data,
                'sound' => 'default',
            ]);
        } catch (\Throwable $e) {
            // A push-server outage should never break the request flow.
            // The scheduled notification remains in the DB for inspection.
            Log::warning('Expo push delivery failed', ['error' => $e->getMessage()]);
        }
    }
}
