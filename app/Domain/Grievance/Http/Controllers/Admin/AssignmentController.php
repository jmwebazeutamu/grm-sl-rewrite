<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Actions\AssignGrievance;
use App\Domain\Grievance\Http\Requests\AssignGrievanceRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AssignmentController extends Controller
{
    public function update(
        AssignGrievanceRequest $request,
        Grievance $grievance,
        AssignGrievance $assign,
    ): RedirectResponse {
        $this->authorize('assign', $grievance);

        $officer = null;
        if ($officerId = $request->integer('officer_id') ?: null) {
            $officer = User::findOrFail($officerId);

            // Officer must belong to the case's owning org. No cross-org
            // reassignment — that's what reclassify is for.
            if ($officer->organization_id !== $grievance->classified_organization_id) {
                return back()->with('error', 'Officer is not a member of the case\'s organization.');
            }
        }

        $assign($grievance, $officer, $request->user());

        return back()->with('success', $officer
            ? "Case assigned to {$officer->name}."
            : 'Case unassigned.');
    }
}
