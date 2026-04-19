<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Http\Requests\TransitionGrievanceRequest;
use App\Domain\Grievance\Http\Resources\AttachmentResource;
use App\Domain\Grievance\Http\Resources\GrievanceResource;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceTimeline;
use App\Domain\Grievance\Services\GrievanceWorkflow;
use App\Domain\Grievance\Services\InvalidTransition;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Region;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType as GrievanceTypeModel;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\Area;
use App\Domain\Reference\Models\CaseConcept;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GrievanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = QueryBuilder::for(Grievance::class)
            ->allowedFilters([
                AllowedFilter::partial('g_number'),
                AllowedFilter::partial('summary'),
                AllowedFilter::exact('state'),
                AllowedFilter::exact('grievance_type_id'),
                AllowedFilter::exact('priority_id'),
                AllowedFilter::exact('region_id'),
            ])
            ->allowedSorts(['received_at', 'g_number'])
            ->defaultSort('-received_at')
            ->with([
                'grievanceType:id,name', 'priority:id,name', 'complainer',
                'region:id,name', 'district:id,name', 'chiefdom:id,name',
                'section:id,name', 'locality:id,name',
                'implementingOrganization:id,name,sla_days', 'programme:id,name,acronym,sla_days',
                'assignedOfficer:id,name',
                'orgClassification:id,label',
                'classifiedOrganization:id,name,acronym,sla_days',
            ])
            ->withCount('suspects');

        // Mirror GrievancePolicy visibility so officers only see their org's
        // cases in the list. Super-admin (Gate::before) and cross-org
        // supervisors (organization_id is null) see everything.
        if (! $user->hasAnyRole(['super-admin', 'grm-data-operator']) && $user->organization_id !== null) {
            $accId = Organization::where('acronym', config('grm.acc_acronym', 'ACC'))->value('id');
            $isAcc = $user->organization_id === $accId;
            $orgId = $user->organization_id;

            $query->where(function ($q) use ($orgId, $isAcc) {
                // Cases the user's org owns (classified to them).
                $q->where('classified_organization_id', $orgId);

                // ACC additionally sees the intake queue.
                if ($isAcc) {
                    $q->orWhereIn('state', ['submitted', 'under_review']);
                }
            });
        }

        // New filters beyond Spatie QueryBuilder's allowed filters.
        $query
            ->when($request->input('search'), fn ($q, $v) => $q->where(
                fn ($inner) => $inner->where('grievance.summary', 'like', "%{$v}%")
                    ->orWhere('grievance.g_number', 'like', "%{$v}%"),
            ))
            ->when($request->input('ref'), fn ($q, $v) => $q->where('grievance.g_number', 'like', "%{$v}%"))
            ->when($request->input('date_from'), fn ($q, $v) => $q->whereDate('grievance.received_at', '>=', $v))
            ->when($request->input('date_to'), fn ($q, $v) => $q->whereDate('grievance.received_at', '<=', $v))
            ->when($request->input('district_id'), fn ($q, $v) => $q->where('grievance.district_id', $v))
            ->when($request->input('grievance_type_id'), fn ($q, $v) => $q->where('grievance.grievance_type_id', $v))
            ->when($request->input('state'), fn ($q, $v) => $q->where('grievance.state', $v));

        if ($request->input('organization_id') && $user->hasAnyRole(['super-admin', 'acc-reviewer', 'grm-data-operator'])) {
            $query->where('grievance.classified_organization_id', $request->input('organization_id'));
        }

        $grievances = $query->paginate(25)->withQueryString();

        $isGlobalRole = $user->hasAnyRole(['super-admin', 'acc-reviewer', 'grm-data-operator']);

        return Inertia::render('Grievance/Admin/Index', [
            'grievances' => GrievanceResource::collection($grievances),
            'filters' => $request->only([
                'search', 'ref', 'date_from', 'date_to',
                'district_id', 'grievance_type_id', 'organization_id',
                'state', 'sla_status',
            ]),
            'states' => collect(GrievanceState::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'districts' => District::orderBy('name')->get(['id', 'name']),
            'grievanceTypes' => GrievanceTypeModel::orderBy('name')->get(['id', 'name']),
            'organizations' => $isGlobalRole ? Organization::orderBy('name')->get(['id', 'name']) : [],
            'canFilterByOrg' => $isGlobalRole,
        ]);
    }

    public function show(
        Request $request,
        Grievance $grievance,
        GrievanceWorkflow $workflow,
        GrievanceTimeline $timeline,
    ): Response {
        $grievance->load([
            'grievanceType', 'howReported', 'priority',
            'region:id,name', 'district:id,name', 'chiefdom:id,name', 'section:id,name', 'locality:id,name',
            'complainer', 'suspects.programme', 'suspects.implementingOrganization', 'beneficiaries',
            'attachments.uploadedBy:id,name', 'assignedOfficer:id,name',
            'closureReviewedBy:id,name',
            'implementingOrganization:id,name,sla_days', 'programme:id,name,acronym,sla_days',
            'classifiedOrganization:id,name,acronym,sla_days',
            'classifiedProgramme:id,name',
            'classifiedCaseConcept:id,name', 'classifiedArea:id,name',
            'access:id,name', 'orgClassification:id,label',
            'reviewedBy:id,name',
        ]);

        $user = $request->user();

        // Officers in the case's owning org — candidates for assignment.
        $officersInOrg = [];
        if ($grievance->classified_organization_id !== null) {
            $officersInOrg = \App\Domain\Identity\Models\User::where(
                'organization_id',
                $grievance->classified_organization_id,
            )
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray();
        }

        // Org grievance types for the sub-classification dropdown.
        $orgGrievanceTypes = [];
        if ($grievance->classified_organization_id !== null) {
            $orgGrievanceTypes = \App\Domain\Grievance\Models\OrgGrievanceType::where(
                'organization_id',
                $grievance->classified_organization_id,
            )
                ->where('active', true)
                ->orderBy('label')
                ->get(['id', 'label'])
                ->toArray();
        }

        // ACC org ID for the categorization panel.
        $accOrgId = Organization::where('acronym', config('grm.acc_acronym', 'ACC'))->value('id');

        return Inertia::render('Grievance/Admin/Show', [
            'grievance' => GrievanceResource::make($grievance),
            'timeline' => $timeline->build($grievance),
            'allowed_transitions' => collect($workflow->allowedFrom($grievance->state))
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'action_types' => collect(ActionType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'organizations' => Organization::orderBy('name')->get(['id', 'name', 'acronym']),
            'acc_org_id' => $accOrgId,
            'org_grievance_types' => $orgGrievanceTypes,
            'capabilities' => [
                'can_edit' => $user->can('update', $grievance),
                'can_transition' => $user->can('transition', $grievance),
                'can_assign' => $user->can('assign', $grievance),
                'can_classify' => $user->can('classify', $grievance),
                'can_review' => $user->can('review', $grievance),
                'can_org_classify' => $user->can('orgClassify', $grievance),
                'can_edit_org_classification' => $user->can('updateOrgClassification', $grievance),
                'can_upload_attachment' => $user->can('uploadAttachment', $grievance),
                'can_delete_attachment' => $user->can('delete', $grievance),
                'can_admin_review' => $user->can('adminReview', $grievance),
                'can_closure_action' => $user->can('closureAction', $grievance),
                'is_org_role' => $user->hasAnyRole(['org-admin', 'grm-officer', 'organization-officer']),
            ],
            'officers_in_org' => $officersInOrg,
            'attachments' => AttachmentResource::collection(
                $grievance->attachments->sortByDesc('created_at')->values()
            )->resolve(),
            'regions' => Region::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function transition(
        TransitionGrievanceRequest $request,
        Grievance $grievance,
        GrievanceWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('transition', $grievance);

        try {
            $workflow->transition(
                $grievance,
                $request->toState(),
                $request->user(),
                $request->input('note'),
            );
        } catch (InvalidTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Grievance moved to {$request->toState()->label()}.");
    }
}
