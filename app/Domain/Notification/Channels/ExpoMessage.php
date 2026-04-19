<?php

declare(strict_types=1);

namespace App\Domain\Notification\Channels;

/**
 * Payload for the Expo push service. `data` is a key-value map delivered
 * to the client so it can deep-link into the right screen on tap.
 */
class ExpoMessage
{
    /** @param array<string, scalar> $data */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {
    }
}
