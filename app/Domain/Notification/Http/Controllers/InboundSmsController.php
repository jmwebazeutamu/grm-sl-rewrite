<?php

declare(strict_types=1);

namespace App\Domain\Notification\Http\Controllers;

use App\Domain\Notification\Services\InboundSmsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Africa's Talking inbound SMS webhook. Provider posts form-encoded:
 *   - from (MSISDN)
 *   - text (message body)
 *   - id (provider message id, optional)
 *
 * Verification: we require a shared-secret header `X-Inbound-Secret`
 * configured on the AT side. AT doesn't HMAC their webhooks, so this is
 * the pragmatic mitigation — plus rate-limiting on the route and IP allow-
 * listing at the web server / firewall level for production.
 */
class InboundSmsController extends Controller
{
    public function __invoke(Request $request, InboundSmsHandler $handler): JsonResponse
    {
        $expected = config('notifications.inbound_sms_secret');

        if (is_string($expected) && $expected !== '') {
            $provided = (string) $request->header('X-Inbound-Secret', '');
            abort_unless(hash_equals($expected, $provided), 403);
        }

        $data = $request->validate([
            'from' => ['required', 'string', 'max:30'],
            'text' => ['required', 'string', 'max:500'],
            'id' => ['nullable', 'string', 'max:100'],
        ]);

        $handler->handle($data['from'], $data['text'], $data['id'] ?? null);

        return response()->json(['ok' => true]);
    }
}
