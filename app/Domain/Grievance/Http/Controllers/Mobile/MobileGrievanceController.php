<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Mobile;

use App\Domain\Grievance\Actions\AcceptGrievance;
use App\Domain\Grievance\Actions\AssignGrievance;
use App\Domain\Grievance\Actions\BeginClosureReview;
use App\Domain\Grievance\Actions\CategorizeGrievance;
use App\Domain\Grievance\Actions\CloseGrievance;
use App\Domain\Grievance\Actions\EscalateAndReopen;
use App\Domain\Grievance\Actions\PostAction;
use App\Domain\Grievance\Actions\RejectGrievance;
use App\Domain\Grievance\Actions\SetOrgClassification;
use App\Domain\Grievance\Actions\SubmitGrievance;
use App\Domain\Grievance\Actions\UploadAttachment;
use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Http\Requests\SubmitGrievanceRequest;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\GrievanceTimeline;
use App\Domain\Grievance\Services\SlaCalculator;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileGrievanceController extends Controller
{
    /**
     * PUBLIC — lookup by reference number. Returns only the fields a
     * complainant with the ref number is entitled to see. No PII from
     * the case file, no officer names, no actor info.
     */
    public function track(string $ref): JsonResponse
    {
        $grievance = Grievance::with([
            'classifiedOrganization:id,name,acronym',
            'statusHistory' => fn ($q) => $q->orderBy('occurred_at'),
        ])->where('g_number', $ref)->first();

        if (! $grievance) {
            return response()->json(['error' => "No grievance found with reference '{$ref}'."], 404);
        }

        return response()->json([
            'grm_number' => $grievance->g_number,
            'summary' => $grievance->summary,
            'state' => $grievance->state->label(),
            'state_value' => $grievance->state->value,
            'submitted_at' => $grievance->received_at?->format('d M Y'),
            'last_updated' => $grievance->updated_at?->format('d M Y'),
            'resolved_at' => $grievance->resolved_at?->format('d M Y'),
            'closed_at' => $grievance->closed_at?->format('d M Y'),
            'assigned_org' => $grievance->classifiedOrganization?->name,
            'timeline' => $grievance->statusHistory->map(fn ($h) => [
                'state' => $h->to_state->label(),
                'state_value' => $h->to_state->value,
                'date' => $h->occurred_at?->format('d M Y'),
            ])->values(),
        ]);
    }

    /**
     * PUBLIC — submit a new grievance. Uses the same FormRequest and Action
     * as the web flow; just returns JSON instead of redirecting.
     */
    public function submit(SubmitGrievanceRequest $request, SubmitGrievance $submit): JsonResponse
    {
        $data = $request->validated();
        unset($data['recaptcha_token'], $data['attachments']);

        $grievance = $submit($data, $request->file('attachments') ?? []);

        return response()->json([
            'grm_number' => $grievance->g_number,
            'state' => $grievance->state->value,
            'state_label' => $grievance->state->label(),
            'received_at' => $grievance->received_at->toIso8601String(),
        ], 201);
    }

    /** AUTH — paginated grievance list, org-scoped per policy. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Grievance::query()
            ->with([
                'grievanceType:id,name',
                'classifiedOrganization:id,name,acronym,sla_days',
                'implementingOrganization:id,name,sla_days',
                'programme:id,name,sla_days',
                'classifiedProgramme:id,name',
                'district:id,name',
                'orgClassification:id,label',
            ])
            ->orderByDesc('received_at');

        // Org scoping — mirrors Admin GrievanceController.
        if (! $user->hasAnyRole(['super-admin', 'grm-data-operator']) && $user->organization_id !== null) {
            $accId = Organization::where('acronym', config('grm.acc_acronym', 'ACC'))->value('id');
            $isAcc = $user->organization_id === $accId;
            $orgId = $user->organization_id;

            $query->where(function ($q) use ($orgId, $isAcc): void {
                $q->where('classified_organization_id', $orgId);
                if ($isAcc) {
                    $q->orWhereIn('state', ['submitted', 'under_review']);
                }
            });
        }

        if ($v = $request->input('search')) {
            $query->where(function ($q) use ($v): void {
                $q->where('summary', 'like', "%{$v}%")->orWhere('g_number', 'like', "%{$v}%");
            });
        }
        if ($v = $request->input('state')) {
            $query->where('state', $v);
        }
        if ($v = $request->input('date_from')) {
            $query->whereDate('received_at', '>=', $v);
        }
        if ($v = $request->input('date_to')) {
            $query->whereDate('received_at', '<=', $v);
        }

        $page = $query->paginate(25)->withQueryString();

        $sla = app(SlaCalculator::class);

        $page->through(function (Grievance $g) use ($sla): array {
            return [
                'id' => $g->id,
                'g_number' => $g->g_number,
                'summary' => $g->summary,
                'state' => $g->state->value,
                'state_label' => $g->state->label(),
                'grievance_type' => $g->grievanceType?->name,
                'organisation' => $g->classifiedOrganization?->acronym
                    ?? $g->classifiedOrganization?->name,
                'programme' => $g->classifiedProgramme?->name,
                'org_classification' => $g->orgClassification?->label,
                'district' => $g->district?->name,
                'received_at' => $g->received_at?->toIso8601String(),
                'days_open' => $g->received_at ? (int) $g->received_at->diffInDays(now()) : null,
                'sla_status' => $sla->calculate($g)['status'],
            ];
        });

        return response()->json($page);
    }

    /** AUTH — grievance detail. */
    public function show(int $id, Request $request, GrievanceTimeline $timeline): JsonResponse
    {
        $grievance = Grievance::with([
            'grievanceType:id,name',
            'howReported:id,name',
            'priority:id,name',
            'region:id,name', 'district:id,name', 'chiefdom:id,name',
            'section:id,name', 'locality:id,name',
            'complainer', 'suspects', 'beneficiaries',
            'attachments.uploadedBy:id,name',
            'assignedOfficer:id,name',
            'classifiedOrganization:id,name,acronym,sla_days',
            'implementingOrganization:id,name,sla_days',
            'classifiedProgramme:id,name',
            'programme:id,name,sla_days',
            'orgClassification:id,label',
        ])->findOrFail($id);

        $this->authorize('view', $grievance);

        $sla = app(SlaCalculator::class);

        return response()->json([
            'id' => $grievance->id,
            'g_number' => $grievance->g_number,
            'summary' => $grievance->summary,
            'description' => $grievance->description,
            'state' => $grievance->state->value,
            'state_label' => $grievance->state->label(),
            'is_anonymous' => $grievance->is_anonymous,
            'grievance_type' => $grievance->grievanceType?->only(['id', 'name']),
            'how_reported' => $grievance->howReported?->only(['id', 'name']),
            'priority' => $grievance->priority?->only(['id', 'name']),
            'organisation' => $grievance->classifiedOrganization ? [
                'id' => $grievance->classifiedOrganization->id,
                'name' => $grievance->classifiedOrganization->name,
                'acronym' => $grievance->classifiedOrganization->getAttribute('acronym'),
            ] : null,
            'programme' => $grievance->classifiedProgramme?->only(['id', 'name']),
            'org_classification' => $grievance->orgClassification ? [
                'id' => $grievance->orgClassification->id,
                'label' => $grievance->orgClassification->label,
            ] : null,
            'location' => [
                'region' => $grievance->region?->name,
                'district' => $grievance->district?->name,
                'chiefdom' => $grievance->chiefdom?->name,
                'section' => $grievance->section?->name,
                'locality' => $grievance->locality?->name,
            ],
            'complainer' => $grievance->complainer ? [
                'first_name' => $grievance->complainer->first_name,
                'last_name' => $grievance->complainer->last_name,
                'phone_number' => $grievance->complainer->phone_number,
                'email' => $grievance->complainer->email,
                'address' => $grievance->complainer->address,
            ] : null,
            'suspects' => $grievance->suspects->map(fn ($s) => [
                'id' => $s->id,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'title' => $s->title,
                'phone_number' => $s->phone_number,
                'is_beneficiary' => $s->is_beneficiary,
                'beneficiary_id_number' => $s->beneficiary_id_number,
            ])->values(),
            'attachments' => $grievance->attachments->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size_bytes' => $a->size_bytes,
                'uploaded_by' => $a->uploadedBy?->name,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values(),
            'assigned_officer' => $grievance->assignedOfficer?->only(['id', 'name']),
            'received_at' => $grievance->received_at?->toIso8601String(),
            'resolved_at' => $grievance->resolved_at?->toIso8601String(),
            'closed_at' => $grievance->closed_at?->toIso8601String(),
            'days_open' => $grievance->received_at ? (int) $grievance->received_at->diffInDays(now()) : null,
            'sla_status' => $sla->calculate($grievance)['status'],
            'sla_days' => $sla->calculate($grievance)['sla_days'],
            'capabilities' => [
                'can_edit' => $request->user()->can('update', $grievance),
                'can_transition' => $request->user()->can('transition', $grievance),
                'can_assign' => $request->user()->can('assign', $grievance),
                'can_classify' => $request->user()->can('classify', $grievance),
                'can_org_classify' => $request->user()->can('orgClassify', $grievance),
                'can_review' => $request->user()->can('review', $grievance),
                'can_upload_attachment' => $request->user()->can('uploadAttachment', $grievance),
                'can_closure_action' => $request->user()->can('closureAction', $grievance),
            ],
            'timeline' => $timeline->build($grievance),
        ]);
    }

    /** AUTH — post an action/comment. */
    public function postAction(int $id, Request $request, PostAction $action): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('update', $grievance);

        $data = $request->validate([
            'type' => ['required', 'string'],
            'body' => ['required', 'string', 'max:10000'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:person,id'],
        ]);

        $actionEntry = $action($grievance, [
            'type' => ActionType::from($data['type']),
            'body' => $data['body'],
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
        ], $request->user());

        return response()->json(['action_id' => $actionEntry->id, 'ok' => true], 201);
    }

    /** AUTH — upload attachment. */
    public function uploadAttachment(int $id, Request $request, UploadAttachment $upload): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('uploadAttachment', $grievance);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Action signature: (Grievance, array $files, ?string $description, User $uploader).
        // Mobile uploads one file at a time; wrap in an array.
        $created = $upload(
            $grievance,
            [$request->file('file')],
            $request->input('description'),
            $request->user(),
        );

        $attachment = $created[0];

        return response()->json([
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'size_bytes' => $attachment->size_bytes,
        ], 201);
    }

    /** AUTH — accept / reject. */
    public function review(int $id, Request $request, AcceptGrievance $accept, RejectGrievance $reject): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('review', $grievance);

        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['decision'] === 'accept') {
            $accept($grievance, $request->user(), $data['comment'] ?? null);
        } else {
            $reject($grievance, $request->user(), $data['comment'] ?? null);
        }

        return response()->json(['ok' => true, 'state' => $grievance->fresh()->state->value]);
    }

    /** AUTH — assign officer. */
    public function assign(int $id, Request $request, AssignGrievance $assign): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('assign', $grievance);

        $data = $request->validate([
            'officer_id' => ['required', 'integer', 'exists:person,id'],
        ]);

        $officer = \App\Domain\Identity\Models\User::findOrFail($data['officer_id']);
        $assign($grievance, $officer, $request->user());

        return response()->json(['ok' => true]);
    }

    /** AUTH — set category + implementing organisation (accepted → assigned). */
    public function categorize(int $id, Request $request, CategorizeGrievance $categorize): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('classify', $grievance);

        $data = $request->validate([
            'category' => ['required', 'in:corruption,administrative'],
            'classified_organization_id' => ['required', 'integer', 'exists:organization,id'],
        ]);

        $categorize($grievance, $data, $request->user());

        return response()->json(['ok' => true, 'state' => $grievance->fresh()->state->value]);
    }

    /** AUTH — set sub-classification (assigned → in_progress). */
    public function classify(int $id, Request $request, SetOrgClassification $classify): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('orgClassify', $grievance);

        $data = $request->validate([
            'org_classification_id' => ['required', 'integer', 'exists:org_grievance_types,id'],
        ]);

        $classify($grievance, $data['org_classification_id'], $request->user());

        return response()->json(['ok' => true, 'state' => $grievance->fresh()->state->value]);
    }

    public function beginClosure(int $id, Request $request, BeginClosureReview $begin): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('closureAction', $grievance);

        $begin($grievance, $request->user());

        return response()->json(['ok' => true, 'state' => $grievance->fresh()->state->value]);
    }

    public function close(int $id, Request $request, CloseGrievance $close): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('closureAction', $grievance);

        $data = $request->validate([
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $close($grievance, $data['comment'], $request->user());

        return response()->json(['ok' => true, 'state' => $grievance->fresh()->state->value]);
    }

    public function escalate(int $id, Request $request, EscalateAndReopen $escalate): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('closureAction', $grievance);

        $data = $request->validate([
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $escalate($grievance, $data['comment'], $request->user());

        return response()->json(['ok' => true, 'state' => $grievance->fresh()->state->value]);
    }

    public function timeline(int $id, Request $request, GrievanceTimeline $timeline): JsonResponse
    {
        $grievance = Grievance::findOrFail($id);
        $this->authorize('view', $grievance);

        return response()->json(['timeline' => $timeline->build($grievance)]);
    }
}
