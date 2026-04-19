<?php

declare(strict_types=1);

namespace App\Domain\Notification\Gateways;

use App\Domain\Notification\Contracts\SmsDispatchResult;
use App\Domain\Notification\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Default gateway in local/test/staging. Logs the message and returns
 * accepted — means developers see what would have been sent without a real
 * provider key, and tests can assert against the log channel.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): SmsDispatchResult
    {
        Log::channel(config('notifications.sms_log_channel', 'stack'))
            ->info('[SMS] → '.$to.': '.$message);

        return SmsDispatchResult::accepted('log-'.bin2hex(random_bytes(6)));
    }
}
