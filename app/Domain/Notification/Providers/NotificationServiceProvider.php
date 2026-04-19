<?php

declare(strict_types=1);

namespace App\Domain\Notification\Providers;

use App\Domain\Notification\Channels\SmsChannel;
use App\Domain\Notification\Contracts\SmsGateway;
use App\Domain\Notification\Gateways\AfricasTalkingGateway;
use App\Domain\Notification\Gateways\LogSmsGateway;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, function ($app): SmsGateway {
            $driver = config('notifications.sms_driver', 'log');
            $username = (string) config('services.africastalking.username', '');
            $apiKey = (string) config('services.africastalking.api_key', '');

            if ($driver === 'africastalking' && $username !== '' && $apiKey !== '') {
                return new AfricasTalkingGateway(
                    $username,
                    $apiKey,
                    (string) config('services.africastalking.sender_id', 'GRM-SL'),
                );
            }

            return new LogSmsGateway;
        });
    }

    public function boot(): void
    {
        $this->app->make(ChannelManager::class)->extend('sms', function ($app) {
            return new SmsChannel($app->make(SmsGateway::class));
        });
    }
}
