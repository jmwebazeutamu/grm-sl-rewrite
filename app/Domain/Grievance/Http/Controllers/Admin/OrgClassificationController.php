<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Actions\SetOrgClassification;
use App\Domain\Grievance\Http\Requests\OrgClassifyRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;

class OrgClassificationController extends Controller
{
    public function update(
        OrgClassifyRequest $request,
        Grievance $grievance,
        SetOrgClassification $classify,
    ): RedirectResponse {
        $this->authorize('transition', $grievance);

        try {
            $classify($grievance, $request->validated()['org_classification_id'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Classification set. Case is now in progress.');
    }

    /**
     * Field-only update — org-admin edits an existing sub-classification
     * without re-running any state transition.
     */
    public function edit(
        OrgClassifyRequest $request,
        Grievance $grievance,
    ): RedirectResponse {
        $this->authorize('updateOrgClassification', $grievance);

        $grievance->update([
            'org_classification_id' => $request->validated()['org_classification_id'],
        ]);

        return back()->with('success', 'Sub-classification updated.');
    }
}
