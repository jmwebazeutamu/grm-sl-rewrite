<?php

declare(strict_types=1);

return [
    /*
    | SMS driver: 'log' (default — writes to application log; use in dev and
    | when AT credentials are unset) or 'africastalking' (live / sandbox).
    */
    'sms_driver' => env('SMS_DRIVER', 'log'),

    'sms_log_channel' => env('SMS_LOG_CHANNEL', 'stack'),

    /*
    | Shared-secret header used to verify inbound SMS webhooks from the
    | provider. Configure the provider's "delivery callback header" to match.
    */
    'inbound_sms_secret' => env('INBOUND_SMS_SECRET'),
];
