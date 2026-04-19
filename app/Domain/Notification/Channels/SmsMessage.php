<?php

declare(strict_types=1);

namespace App\Domain\Notification\Channels;

/**
 * Plain value object returned from a notification's `toSms()`. Kept
 * intentionally minimal — no templating, no encoding options. If a
 * notification needs conditional logic it belongs in `toSms()`, not here.
 */
final class SmsMessage
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $to = null,
    ) {
    }
}
