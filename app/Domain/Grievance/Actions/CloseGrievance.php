<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Identity\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CloseGrievance
{
    public function __construct(private readonly GrievanceWorkflow $workflow)
    {
    }

    public function __invoke(Grievance $grievance, string $closureComment, User $reviewer): Grievance
    {
        if ($grievance->state !== GrievanceState::UnderAdminReview) {
            throw new DomainException('A grievance can only be closed from the under_admin_review state.');
        }

        return DB::transaction(function () use ($grievance, $closureComment, $reviewer): Grievance {
            $grievance->closure_comment = $closureComment;
            $grievance->save();

            return $this->workflow->transition(
                $grievance,
                GrievanceState::Closed,
                $reviewer,
                $closureComment,
            );
        });
    }
}
