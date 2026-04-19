<?php

declare(strict_types=1);

use App\Domain\Notification\Contracts\SmsGateway;
use App\Domain\Notification\Gateways\AfricasTalkingGateway;
use App\Domain\Notification\Gateways\LogSmsGateway;
use Illuminate\Support\Facades\Http;

it('binds the Log gateway when SMS_DRIVER is log', function (): void {
    config(['notifications.sms_driver' => 'log']);
    $app = app();
    $app->forgetInstance(SmsGateway::class);

    expect($app->make(SmsGateway::class))->toBeInstanceOf(LogSmsGateway::class);
});

it('falls back to Log when AT credentials are missing', function (): void {
    config([
        'notifications.sms_driver' => 'africastalking',
        'services.africastalking.username' => '',
        'services.africastalking.api_key' => '',
    ]);
    $app = app();
    $app->forgetInstance(SmsGateway::class);

    expect($app->make(SmsGateway::class))->toBeInstanceOf(LogSmsGateway::class);
});

it('binds Africa\'s Talking gateway when configured', function (): void {
    config([
        'notifications.sms_driver' => 'africastalking',
        'services.africastalking.username' => 'sandbox',
        'services.africastalking.api_key' => 'key-xxx',
        'services.africastalking.sender_id' => 'GRM-SL',
    ]);
    $app = app();
    $app->forgetInstance(SmsGateway::class);

    expect($app->make(SmsGateway::class))->toBeInstanceOf(AfricasTalkingGateway::class);
});

it('posts to the AT endpoint and returns accepted on success', function (): void {
    Http::fake([
        'sandbox.africastalking.com/*' => Http::response([
            'SMSMessageData' => [
                'Recipients' => [[
                    'status' => 'Success',
                    'messageId' => 'ATXid_abc',
                ]],
            ],
        ], 200),
    ]);

    $gateway = new AfricasTalkingGateway('sandbox', 'key', 'GRM-SL');
    $result = $gateway->send('+23276000000', 'Hello');

    expect($result->accepted)->toBeTrue();
    expect($result->providerMessageId)->toBe('ATXid_abc');
});
