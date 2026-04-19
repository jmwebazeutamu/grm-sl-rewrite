<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Two-step transition: accepted → categorized → assigned.
 *
 * Sets `category` (corruption | administrative) and `classified_organization_id`,
 * then runs both transitions in a single transaction. The workflow validates
 * the category-to-org mapping (corruption → ACC only).
 */
class CategorizeGrievance
{
    public function __construct(private readonly GrievanceWorkflow $workflow)
    {
    }

    /**
     * @param array{category: string, classified_organization_id: int} $data
     */
    public function __invoke(Grievance $grievance, array $data, User $actor): Grievance
    {
        return DB::transaction(function () use ($grievance, $data, $actor): Grievance {
            // Set the fields BEFORE transitioning — workflow validates them.
            $grievance->category = $data['category'];
            $grievance->classified_organization_id = $data['classified_organization_id'];
            $grievance->save();

            // Step 1: accepted → categorized (validates category is set)
            $this->workflow->transition($grievance, GrievanceState::Categorized, $actor, "Category: {$data['category']}");

            // Step 2: categorized → assigned (validates org matches category rule)
            $org = Organization::find($data['classified_organization_id']);
            $orgName = $org?->name ?? 'Unknown';
            $this->workflow->transition($grievance, GrievanceState::Assigned, $actor, "Assigned to {$orgName}");

            return $grievance->refresh();
        });
    }
}
