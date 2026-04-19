<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Services;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceStatusHistory;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Sole owner of grievance state transitions. All controllers, actions, and
 * jobs MUST go through `transition()` — never set `$grievance->state` directly.
 *
 * The new pipeline: submitted → under_review → accepted → categorized →
 * assigned → org_classified → in_progress → resolved → closed.
 *
 * Transition validation rules:
 *   - accepted → categorized: grievance.category must be set
 *   - categorized → assigned: classified_organization_id must be set AND
 *     match the category rule (corruption → ACC only)
 *   - assigned → org_classified: org_classification_id must be set
 *   - rejected is terminal — throw InvalidTransition for any outbound
 */
class GrievanceWorkflow
{
    /** @var array<string, array<int, GrievanceState>> */
    private const TRANSITIONS = [
        'submitted' => [
            GrievanceState::UnderReview,
            GrievanceState::Trashed,
        ],
        'under_review' => [
            GrievanceState::Accepted,
            GrievanceState::Rejected,
            GrievanceState::Trashed,
        ],
        'accepted' => [
            GrievanceState::Categorized,
        ],
        'categorized' => [
            GrievanceState::Assigned,
        ],
        'assigned' => [
            GrievanceState::OrgClassified,
        ],
        'org_classified' => [
            GrievanceState::InProgress,
        ],
        'in_progress' => [
            GrievanceState::Resolved,
            GrievanceState::Trashed,
        ],
        'resolved' => [
            GrievanceState::UnderAdminReview,
        ],
        'under_admin_review' => [
            GrievanceState::Closed,
            GrievanceState::Escalated,
        ],
        'escalated' => [
            GrievanceState::Reopened,
        ],
        'reopened' => [
            GrievanceState::InProgress,
        ],
        // rejected, trashed, closed → [] (terminal, no key = empty)
    ];

    public function canTransition(GrievanceState $from, GrievanceState $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /** @return list<GrievanceState> */
    public function allowedFrom(GrievanceState $state): array
    {
        return self::TRANSITIONS[$state->value] ?? [];
    }

    public function transition(
        Grievance $grievance,
        GrievanceState $to,
        ?User $actor = null,
        ?string $note = null,
    ): Grievance {
        $from = $grievance->state;

        if ($from === $to) {
            return $grievance;
        }

        if (! $this->canTransition($from, $to)) {
            throw new InvalidTransition($from, $to);
        }

        // Pre-transition validation rules for the pipeline states.
        $this->validatePipelineRules($grievance, $to);

        return DB::transaction(function () use ($grievance, $from, $to, $actor, $note): Grievance {
            $grievance->state = $to;

            // Timestamp stamps per state.
            if ($to === GrievanceState::Accepted) {
                $grievance->accepted_at = now();
                $grievance->reviewed_by_id = $actor?->id;
            }

            if ($to === GrievanceState::Rejected) {
                $grievance->reviewed_at = now();
                $grievance->reviewed_by_id = $actor?->id;
                if ($note !== null) {
                    $grievance->review_comment = $note;
                }
            }

            if ($to === GrievanceState::Categorized) {
                $grievance->categorized_at = now();
            }

            if ($to === GrievanceState::Assigned) {
                $grievance->assigned_at = now();
            }

            if ($to === GrievanceState::Resolved) {
                $grievance->resolved_at = now();
            }

            if ($to === GrievanceState::Closed) {
                $grievance->closed_at = now();
            }

            if ($to === GrievanceState::UnderAdminReview) {
                $grievance->closure_reviewed_by_id = $actor?->id;
                $grievance->closure_reviewed_at = now();
            }

            if ($to === GrievanceState::Reopened) {
                $grievance->reopened_at = now();
            }

            $grievance->save();

            GrievanceStatusHistory::create([
                'grievance_id' => $grievance->id,
                'from_state' => $from->value,
                'to_state' => $to->value,
                'note' => $note,
                'actor_id' => $actor?->id,
                'occurred_at' => now(),
            ]);

            GrievanceStateChanged::dispatch($grievance, $from, $to, $note);

            return $grievance;
        });
    }

    /**
     * Enforce data requirements before allowing pipeline transitions.
     * These are hard rules that live in the workflow, not the policy.
     */
    private function validatePipelineRules(Grievance $grievance, GrievanceState $to): void
    {
        if ($to === GrievanceState::Categorized && empty($grievance->category)) {
            throw new DomainException('Category (corruption or administrative) must be set before categorizing.');
        }

        if ($to === GrievanceState::Assigned) {
            if ($grievance->classified_organization_id === null) {
                throw new DomainException('Organization assignment is required before assigning.');
            }

            // Enforce: corruption → ACC only.
            if ($grievance->category === 'corruption') {
                $accId = Organization::where('acronym', config('grm.acc_acronym', 'ACC'))->value('id');
                if ($grievance->classified_organization_id !== $accId) {
                    throw new DomainException('Corruption cases must be assigned to the Anti-Corruption Commission.');
                }
            }

            // Enforce: administrative → NOT ACC.
            if ($grievance->category === 'administrative') {
                $accId = Organization::where('acronym', config('grm.acc_acronym', 'ACC'))->value('id');
                if ($grievance->classified_organization_id === $accId) {
                    throw new DomainException('Administrative cases cannot be assigned to ACC.');
                }
            }
        }

        if ($to === GrievanceState::OrgClassified && $grievance->org_classification_id === null) {
            throw new DomainException('Org sub-classification is required before marking as org-classified.');
        }
    }
}
