<?php

declare(strict_types=1);

namespace App\Domain\Organization\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Actions\SaveOrganization;
use App\Domain\Organization\Http\Requests\StoreOrganizationRequest;
use App\Domain\Organization\Http\Requests\UpdateOrganizationRequest;
use App\Domain\Organization\Http\Resources\OrganizationResource;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $organizations = QueryBuilder::for(Organization::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('parent_id'),
            ])
            ->allowedSorts(['name'])
            ->defaultSort('name')
            ->withCount(['offices'])
            ->with('parent:id,name')
            ->paginate(25)
            ->withQueryString();

        $orgIds = $organizations->pluck('id');

        $userCounts = User::whereIn('organization_id', $orgIds)
            ->selectRaw('organization_id, COUNT(*) as cnt')
            ->groupBy('organization_id')
            ->pluck('cnt', 'organization_id');

        $admins = User::whereIn('organization_id', $orgIds)
            ->whereHas('roles', fn ($q) => $q->where('name', 'org-admin'))
            ->get(['id', 'name', 'organization_id'])
            ->groupBy('organization_id');

        return Inertia::render('Organization/Index', [
            'organizations' => OrganizationResource::collection($organizations),
            'user_counts' => $userCounts,
            'admins' => $admins->map(fn ($group) => $group->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->values()),
            'filters' => $request->only('filter'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Organization/Create', [
            'parents' => Organization::orderBy('name')->get(['id', 'name']),
            'grievanceTypes' => GrievanceType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreOrganizationRequest $request, SaveOrganization $save): RedirectResponse
    {
        $organization = $save($request->validated());

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'Organization created.');
    }

    public function show(Organization $organization): Response
    {
        $organization->load(['parent:id,name', 'grievanceTypes', 'offices', 'programmes']);

        $users = User::where('organization_id', $organization->id)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'organization_id'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'role' => $u->roles->first()?->name ?? '—',
                'is_admin' => $u->hasRole('org-admin'),
            ]);

        $usage = [
            'offices' => DB::table('office')->where('organization_id', $organization->id)->whereNull('deleted_at')->count(),
            'programmes' => DB::table('programme')->where('organization_id', $organization->id)->whereNull('deleted_at')->count(),
            'employees' => DB::table('employee')->where('organization_id', $organization->id)->whereNull('deleted_at')->count(),
            'users' => DB::table('person')->where('organization_id', $organization->id)->count(),
            'grievancesClassified' => DB::table('grievance')->where('classified_organization_id', $organization->id)->count(),
            'grievancesImplementing' => DB::table('grievance')->where('implementing_organization_id', $organization->id)->count(),
            'children' => DB::table('organization')->where('parent_id', $organization->id)->whereNull('deleted_at')->count(),
        ];

        return Inertia::render('Organization/Show', [
            'organization' => OrganizationResource::make($organization),
            'users' => $users,
            'usage' => $usage,
        ]);
    }

    public function edit(Organization $organization): Response
    {
        $organization->load('grievanceTypes:id');

        return Inertia::render('Organization/Edit', [
            'organization' => OrganizationResource::make($organization),
            'parents' => Organization::where('id', '!=', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'grievanceTypes' => GrievanceType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization, SaveOrganization $save): RedirectResponse
    {
        $save($request->validated(), $organization);

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'Organization updated.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $name = $organization->name;
        try {
            $organization->delete();
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'FOREIGN KEY constraint failed')
                || str_contains($e->getMessage(), 'foreign key constraint')) {
                return redirect()
                    ->route('admin.organizations.show', $organization)
                    ->with('error', 'Cannot delete — a related record prevents deletion.');
            }
            throw $e;
        }

        return redirect()
            ->route('admin.organizations.index')
            ->with('success', "Organization \"{$name}\" deleted.");
    }
}
