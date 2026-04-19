<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\OrgGrievanceType;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Two-step transition: assigned → org_classified → in_progress.
 *
 * Sets the org's internal sub-classification, then runs both transitions.
 * This is the step that "unlocks" case work for org officers.
 */
class SetOrgClassification
{
    public function __construct(private readonly GrievanceWorkflow $workflow)
    {
    }

    public function __invoke(Grievance $grievance, int $orgClassificationId, User $actor): Grievance
    {
        return DB::transaction(function () use ($grievance, $orgClassificationId, $actor): Grievance {
            $type = OrgGrievanceType::findOrFail($orgClassificationId);

            $grievance->org_classification_id = $type->id;
            $grievance->save();

            // Step 1: assigned → org_classified (validates org_classification_id set)
            $this->workflow->transition($grievance, GrievanceState::OrgClassified, $actor, "Classified as: {$type->label}");

            // Step 2: org_classified → in_progress (unlocks case work)
            $this->workflow->transition($grievance, GrievanceState::InProgress, $actor, 'Case work begins.');

            return $grievance->refresh();
        });
    }
}
