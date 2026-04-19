<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Accepts a grievance for categorization. If the case is still in
 * `submitted`, auto-chains `submitted → under_review → accepted`
 * so the reviewer doesn't need a separate "pick up" click.
 */
class AcceptGrievance
{
    public function __construct(private readonly GrievanceWorkflow $workflow)
    {
    }

    public function __invoke(Grievance $grievance, User $actor): Grievance
    {
        return DB::transaction(function () use ($grievance, $actor): Grievance {
            // Auto-advance from submitted if needed.
            if ($grievance->state === GrievanceState::Submitted) {
                $this->workflow->transition(
                    $grievance,
                    GrievanceState::UnderReview,
                    $actor,
                    'Picked up for review.',
                );
            }

            return $this->workflow->transition(
                $grievance,
                GrievanceState::Accepted,
                $actor,
                'Case accepted for categorization.',
            );
        });
    }
}
