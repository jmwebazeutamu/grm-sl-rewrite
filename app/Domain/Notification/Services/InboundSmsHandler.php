<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Grievance\Models\Grievance;
use App\Domain\Notification\Contracts\SmsGateway;
use App\Domain\Notification\Models\SmsInbox;

/**
 * Parses inbound SMS. Today the only supported command is:
 *
 *   STATUS GRM-YYYY-NNNNNN
 *
 * Anything else gets a generic help reply. Keep new commands small and
 * documented — inbound SMS isn't meant to become an IVR.
 */
class InboundSmsHandler
{
    public function __construct(private readonly SmsGateway $gateway)
    {
    }

    public function handle(string $from, string $body, ?string $providerMessageId = null): void
    {
        $normalized = trim(strtoupper($body));

        $grievanceId = null;
        $command = null;
        $reply = $this->helpReply();

        if (preg_match('/^STATUS\s+(GRM-\d{4}-\d{6})$/', $normalized, $m) === 1) {
            $command = 'STATUS';
            $grievance = Grievance::where('g_number', $m[1])->first();
            $grievanceId = $grievance?->id;
            $reply = $grievance
                ? $this->statusReply($grievance)
                : "GRM-SL: No case found for reference {$m[1]}. Check the number and try again.";
        }

        SmsInbox::create([
            'from' => $from,
            'body' => $body,
            'provider_message_id' => $providerMessageId,
            'parsed_command' => $command,
            'matched_grievance_id' => $grievanceId,
            'received_at' => now(),
        ]);

        $this->gateway->send($from, $reply);
    }

    private function statusReply(Grievance $grievance): string
    {
        $label = strtolower($grievance->state->label());

        return "GRM-SL: Case {$grievance->g_number} is {$label}. "
            ."Received ".$grievance->received_at->format('d M Y').'.';
    }

    private function helpReply(): string
    {
        return "GRM-SL: To check a case, text: STATUS <your reference number>. "
            ."Example: STATUS GRM-2026-000123.";
    }
}
