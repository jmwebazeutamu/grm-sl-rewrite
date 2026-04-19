<?php

declare(strict_types=1);

namespace App\Domain\Notification\Gateways;

use App\Domain\Notification\Contracts\SmsDispatchResult;
use App\Domain\Notification\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Africa's Talking bulk SMS. Sierra Leone is in their supported region and
 * their sandbox is free for testing — switch with AT_USERNAME=sandbox.
 */
class AfricasTalkingGateway implements SmsGateway
{
    public function __construct(
        private readonly string $username,
        private readonly string $apiKey,
        private readonly string $senderId,
    ) {
    }

    public function send(string $to, string $message): SmsDispatchResult
    {
        $endpoint = $this->username === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        try {
            $response = Http::withHeaders([
                'ApiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->asForm()
                ->timeout(10)
                ->post($endpoint, [
                    'username' => $this->username,
                    'to' => $to,
                    'message' => $message,
                    'from' => $this->senderId,
                ]);
        } catch (\Throwable $e) {
            Log::warning('SMS dispatch failed', ['to' => $to, 'error' => $e->getMessage()]);

            return SmsDispatchResult::rejected($e->getMessage());
        }

        if (! $response->successful()) {
            Log::warning('SMS gateway returned error', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return SmsDispatchResult::rejected("HTTP {$response->status()}");
        }

        $recipient = $response->json('SMSMessageData.Recipients.0');
        $status = $recipient['status'] ?? 'Unknown';
        $id = $recipient['messageId'] ?? null;

        if (! in_array($status, ['Success', 'Sent'], true)) {
            return SmsDispatchResult::rejected($status);
        }

        return SmsDispatchResult::accepted($id);
    }
}
