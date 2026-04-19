<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Events\FeedbackRequested;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceFeedbackToken;
use Illuminate\Support\Str;

/**
 * Issues a one-shot feedback token and dispatches the FeedbackRequested
 * event. The Phase 5 SMS/email listener picks up the event and sends the
 * signed URL.
 */
class IssueFeedbackToken
{
    public function __invoke(Grievance $grievance, int $ttlDays = 30): GrievanceFeedbackToken
    {
        $token = GrievanceFeedbackToken::create([
            'grievance_id' => $grievance->id,
            'token' => Str::random(48),
            'expires_at' => now()->addDays($ttlDays),
        ]);

        FeedbackRequested::dispatch($grievance, $token);

        return $token;
    }
}
