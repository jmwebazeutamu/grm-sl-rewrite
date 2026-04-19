<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAction;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Posts a timeline action. A Resolve action automatically transitions the
 * case to Resolved and triggers feedback-link generation. This keeps the
 * happy path "officer types what they did" — no separate "apply transition"
 * click.
 */
class PostAction
{
    public function __construct(
        private readonly GrievanceWorkflow $workflow,
        private readonly IssueFeedbackToken $issueToken,
    ) {
    }

    /**
     * @param  array{type: ActionType, body: string, assigned_to_id?: ?int}  $data
     */
    public function __invoke(Grievance $grievance, array $data, User $actor): GrievanceAction
    {
        return DB::transaction(function () use ($grievance, $data, $actor): GrievanceAction {
            $action = $grievance->actions()->create([
                'type' => $data['type'],
                'body' => $data['body'],
                'assigned_to_id' => $data['assigned_to_id'] ?? null,
            ]);

            // Side effects tied to the action type:
            if ($data['type'] === ActionType::Resolve && $grievance->state === GrievanceState::InProgress) {
                $this->workflow->transition($grievance, GrievanceState::Resolved, $actor, $data['body']);
                $grievance->refresh();
                ($this->issueToken)($grievance);
            }

            // The old auto-advance from UnderReview → InProgress on
            // Investigate/Contact has been removed. The new pipeline is:
            // accepted → categorized → assigned → org_classified → in_progress.
            // Actions can only be posted after in_progress.

            if ($data['assigned_to_id'] ?? false) {
                $grievance->update(['assigned_officer_id' => $data['assigned_to_id']]);
            }

            return $action->fresh(['createdBy', 'assignedTo']);
        });
    }
}
