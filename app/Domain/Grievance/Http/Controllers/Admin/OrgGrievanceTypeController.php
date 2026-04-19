<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Models\OrgGrievanceType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgGrievanceTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $types = $orgId === null
            ? OrgGrievanceType::with('organization:id,name')->orderBy('label')->get()
            : OrgGrievanceType::where('organization_id', $orgId)->orderBy('label')->get();

        return Inertia::render('OrgSettings/GrievanceTypes/Index', [
            'types' => $types,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $orgId = $request->user()->organization_id;
        abort_if($orgId === null, 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
        ]);

        OrgGrievanceType::create([
            'organization_id' => $orgId,
            'label' => $data['label'],
        ]);

        return back()->with('success', 'Grievance type added.');
    }

    public function update(Request $request, OrgGrievanceType $type): RedirectResponse
    {
        abort_unless(
            $request->user()->organization_id === null || $type->organization_id === $request->user()->organization_id,
            403,
        );

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:150'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $type->update($data);

        return back()->with('success', 'Updated.');
    }

    public function destroy(Request $request, OrgGrievanceType $type): RedirectResponse
    {
        abort_unless(
            $request->user()->organization_id === null || $type->organization_id === $request->user()->organization_id,
            403,
        );

        $type->delete();

        return back()->with('success', 'Deleted.');
    }
}
