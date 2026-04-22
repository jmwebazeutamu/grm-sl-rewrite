<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Events\GrievanceOfficerAssigned;
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
        $previousOfficerId = $grievance->assigned_officer_id;

        $result = DB::transaction(function () use ($grievance, $officer, $actor, $previousOfficerId): Grievance {
            $grievance->update(['assigned_officer_id' => $officer?->id]);

            $body = $officer === null
                ? "Unassigned (was officer #{$previousOfficerId})."
                : ($previousOfficerId === null
                    ? "Assigned to {$officer->name}."
                    : "Reassigned to {$officer->name} (was officer #{$previousOfficerId}).");

            // created_by_id / updated_by_id are stamped by RecordsAuthorship
            // trait on save; no need to pass them here.
            $grievance->actions()->create([
                'type' => ActionType::Update->value,
                'body' => $body,
                'assigned_to_id' => $officer?->id,
            ]);

            return $grievance->fresh(['assignedOfficer']);
        });

        // Dispatched on (re)assignment only — unassigning shouldn't fire a
        // "you've been assigned" email. Listeners notify the officer + org admins.
        if ($officer !== null) {
            GrievanceOfficerAssigned::dispatch($result, $officer, $actor, $previousOfficerId);
        }

        return $result;
    }
}
