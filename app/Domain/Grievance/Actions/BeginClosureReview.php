<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Identity\Models\User;
use DomainException;

class BeginClosureReview
{
    public function __construct(private readonly GrievanceWorkflow $workflow)
    {
    }

    public function __invoke(Grievance $grievance, User $reviewer): Grievance
    {
        if ($grievance->state !== GrievanceState::Resolved) {
            throw new DomainException('Closure review can only start from the resolved state.');
        }

        return $this->workflow->transition(
            $grievance,
            GrievanceState::UnderAdminReview,
            $reviewer,
            null,
        );
    }
}
