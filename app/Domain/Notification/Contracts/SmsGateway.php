<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

interface SmsGateway
{
    /**
     * Send an SMS. Implementations must swallow/log transient errors — the
     * queue worker retries via the notification job.
     *
     * @param  string  $to  E.164 phone number
     */
    public function send(string $to, string $message): SmsDispatchResult;
}
