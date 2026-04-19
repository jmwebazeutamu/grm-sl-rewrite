<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

final class SmsDispatchResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $reason = null,
    ) {
    }

    public static function accepted(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
