<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Assigns (or reassigns) a grievance to a specific officer. Both the
 * previous and new officer must belong to the grievance's owning org;
 * that check is in the controller/policy layer — this action trusts its
 * inputs and records the change in the timeline.
 */
class AssignGrievance
{
    public function __invoke(Grievance $grievance, ?User $officer, User $actor): Grievance
    {
        return DB::transaction(function () use ($grievance, $officer, $actor): Grievance {
            $previous = $grievance->assigned_officer_id;
            $grievance->update(['assigned_officer_id' => $officer?->id]);

            $body = $officer === null
                ? "Unassigned (was officer #{$previous})."
                : ($previous === null
                    ? "Assigned to {$officer->name}."
                    : "Reassigned to {$officer->name} (was officer #{$previous}).");

            // created_by_id / updated_by_id are stamped by RecordsAuthorship
            // trait on save; no need to pass them here.
            $grievance->actions()->create([
                'type' => ActionType::Update->value,
                'body' => $body,
                'assigned_to_id' => $officer?->id,
            ]);

            return $grievance->fresh(['assignedOfficer']);
        });
    }
}
