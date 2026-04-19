<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceReopened;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Identity\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class EscalateAndReopen
{
    public function __construct(private readonly GrievanceWorkflow $workflow)
    {
    }

    public function __invoke(Grievance $grievance, string $closureComment, User $reviewer): Grievance
    {
        if ($grievance->state !== GrievanceState::UnderAdminReview) {
            throw new DomainException('Escalation can only happen from the under_admin_review state.');
        }

        $result = DB::transaction(function () use ($grievance, $closureComment, $reviewer): Grievance {
            $grievance->closure_comment = $closureComment;
            $grievance->save();

            $this->workflow->transition(
                $grievance,
                GrievanceState::Escalated,
                $reviewer,
                $closureComment,
            );

            return $this->workflow->transition(
                $grievance,
                GrievanceState::Reopened,
                $reviewer,
                null,
            );
        });

        GrievanceReopened::dispatch($result, $reviewer, $closureComment);

        return $result;
    }
}
