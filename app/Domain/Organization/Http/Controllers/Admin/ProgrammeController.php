<?php

declare(strict_types=1);

namespace App\Domain\Organization\Http\Controllers\Admin;

use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\Suspect;
use App\Domain\Organization\Enums\ProgrammeStatus;
use App\Domain\Organization\Http\Requests\StoreProgrammeRequest;
use App\Domain\Organization\Http\Requests\UpdateProgrammeRequest;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgrammeController extends Controller
{
    public function index(Request $request, ?Organization $organization = null): Response
    {
        $this->authorize('viewAny', Programme::class);

        $actor = $request->user();
        $query = Programme::query()->with('organization:id,name')->orderBy('name');

        if ($organization && $organization->exists) {
            $query->where('organization_id', $organization->id);
        } elseif (! $actor->hasRole('super-admin') && $actor->organization_id !== null) {
            $query->where('organization_id', $actor->organization_id);
        }

        return Inertia::render('OrgSettings/Programmes/Index', [
            'programmes' => $query->get(['id', 'name', 'acronym', 'organization_id', 'status'])->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'acronym' => $p->acronym,
                'status' => $p->status?->value ?? ProgrammeStatus::Active->value,
                'organization_id' => $p->organization_id,
                'organization' => $p->organization ? [
                    'id' => $p->organization->id,
                    'name' => $p->organization->name,
                ] : null,
            ]),
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'statuses' => collect(ProgrammeStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'is_super_admin' => (bool) $actor->hasRole('super-admin'),
            'actor_organization_id' => $actor->organization_id,
            'can_manage' => (bool) $actor->hasAnyRole(['org-admin', 'super-admin']),
        ]);
    }

    public function store(StoreProgrammeRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('create', Programme::class);

        $actor = $request->user();
        $targetOrg = $actor->hasRole('super-admin') ? $organization : $actor->organization;

        abort_if($targetOrg === null, 422, 'Organization is required.');

        $data = $request->validated();

        Programme::create([
            'name' => $data['name'],
            'acronym' => $data['acronym'] ?? null,
            'organization_id' => $targetOrg->id,
            'status' => $data['status'],
            'active' => $data['status'] === ProgrammeStatus::Active->value,
        ]);

        return back()->with('success', 'Programme created.');
    }

    public function update(UpdateProgrammeRequest $request, Organization $organization, Programme $programme): RedirectResponse
    {
        $this->authorize('update', $programme);

        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $data['active'] = $data['status'] === ProgrammeStatus::Active->value;
        }

        $programme->update($data);

        return back()->with('success', 'Programme updated.');
    }

    public function destroy(Organization $organization, Programme $programme): RedirectResponse
    {
        $this->authorize('delete', $programme);

        $grievanceUses = Grievance::where('programme_id', $programme->id)
            ->orWhere('classified_programme_id', $programme->id)
            ->count();

        $suspectUses = Suspect::where('programme_id', $programme->id)->count();

        if ($grievanceUses + $suspectUses > 0) {
            $parts = [];
            if ($grievanceUses > 0) {
                $parts[] = "{$grievanceUses} grievance".($grievanceUses === 1 ? '' : 's');
            }
            if ($suspectUses > 0) {
                $parts[] = "{$suspectUses} beneficiary record".($suspectUses === 1 ? '' : 's');
            }
            $summary = implode(' and ', $parts);

            return back()->with(
                'error',
                "Cannot delete \"{$programme->name}\" — it is linked to {$summary}. Mark it as closed instead to hide it from new submissions.",
            );
        }

        $programme->delete();

        return back()->with('success', 'Programme deleted.');
    }
}
