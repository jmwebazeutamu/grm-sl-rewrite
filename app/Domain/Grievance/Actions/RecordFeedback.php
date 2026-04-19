<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\FeedbackRating;
use App\Domain\Grievance\Models\GrievanceFeedback;
use App\Domain\Grievance\Models\GrievanceFeedbackToken;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Records complainant feedback via a redeemed token. The feedback is
 * stored and shown to the reviewer; transitioning out of `resolved` now
 * goes through the admin closure review workflow (resolved →
 * under_admin_review → closed/escalated+reopened) rather than happening
 * automatically from the rating.
 */
class RecordFeedback
{
    /**
     * @param  array{rating: FeedbackRating, comment?: ?string, channel?: string}  $data
     */
    public function __invoke(GrievanceFeedbackToken $token, array $data): GrievanceFeedback
    {
        if (! $token->isUsable()) {
            throw new DomainException('This feedback link is no longer valid.');
        }

        return DB::transaction(function () use ($token, $data): GrievanceFeedback {
            $grievance = $token->grievance;

            $feedback = GrievanceFeedback::create([
                'grievance_id' => $grievance->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'channel' => $data['channel'] ?? 'web',
                'submitted_at' => now(),
            ]);

            $token->consume();

            return $feedback;
        });
    }
}
