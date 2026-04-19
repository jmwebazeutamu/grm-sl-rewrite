<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Resources;

use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Services\SlaCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Grievance */
class GrievanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $canViewPii = $request->user()?->can('grievance.view_pii') ?? false;
        $exposePii = $canViewPii || ! $this->is_anonymous;
        $sla = app(SlaCalculator::class)->calculate($this->resource);

        return [
            'id' => $this->id,
            'g_number' => $this->g_number,
            'summary' => $this->summary,
            'description' => $this->description,
            'state' => [
                'value' => $this->state->value,
                'label' => $this->state->label(),
                'is_terminal' => $this->state->isTerminal(),
            ],
            'is_anonymous' => $this->is_anonymous,
            'category' => $this->category,
            'org_classification' => $this->whenLoaded('orgClassification', fn () => $this->orgClassification ? [
                'id' => $this->orgClassification->id,
                'label' => $this->orgClassification->label,
            ] : null),
            'implementing_organization_id' => $this->implementing_organization_id,
            'implementing_organization' => $this->whenLoaded('implementingOrganization', fn () => $this->implementingOrganization ? [
                'id' => $this->implementingOrganization->id,
                'name' => $this->implementingOrganization->name,
            ] : null),
            'programme_id' => $this->programme_id,
            'programme' => $this->whenLoaded('programme', fn () => $this->programme ? [
                'id' => $this->programme->id,
                'name' => $this->programme->name,
                'acronym' => $this->programme->acronym,
            ] : null),
            'classified_organization' => $this->whenLoaded('classifiedOrganization', fn () => $this->classifiedOrganization ? [
                'id' => $this->classifiedOrganization->id,
                'name' => $this->classifiedOrganization->name,
            ] : null),
            'assigned_officer_id' => $this->assigned_officer_id,
            'assigned_officer' => $this->whenLoaded('assignedOfficer', fn () => $this->assignedOfficer ? [
                'id' => $this->assignedOfficer->id,
                'name' => $this->assignedOfficer->name,
            ] : null),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy ? [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ] : null),
            'review_comment' => $this->review_comment,
            'closure_comment' => $this->closure_comment,
            'closure_reviewed_at' => $this->closure_reviewed_at?->toIso8601String(),
            'closure_reviewed_by' => $this->whenLoaded('closureReviewedBy', fn () => $this->closureReviewedBy ? [
                'id' => $this->closureReviewedBy->id,
                'name' => $this->closureReviewedBy->name,
            ] : null),
            'reopened_at' => $this->reopened_at?->toIso8601String(),
            'location' => [
                'region_id' => $this->region_id,
                'district_id' => $this->district_id,
                'chiefdom_id' => $this->chiefdom_id,
                'section_id' => $this->section_id,
                'locality_id' => $this->locality_id,
                'region' => $this->region?->only(['id', 'name']),
                'district' => $this->district?->only(['id', 'name']),
                'chiefdom' => $this->chiefdom?->only(['id', 'name']),
                'section' => $this->section?->only(['id', 'name']),
                'locality' => $this->locality?->only(['id', 'name']),
                'label' => $this->locationLabel(),
            ],
            'received_at' => $this->received_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'categorized_at' => $this->categorized_at?->toIso8601String(),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'grievance_type' => $this->whenLoaded('grievanceType', fn () => [
                'id' => $this->grievanceType->id,
                'name' => $this->grievanceType->name,
            ]),
            'priority' => $this->whenLoaded('priority', fn () => $this->priority ? [
                'id' => $this->priority->id,
                'name' => $this->priority->name,
            ] : null),
            'complainer' => $this->whenLoaded('complainer', function () use ($exposePii) {
                if (! $this->complainer) {
                    return null;
                }

                return $exposePii ? [
                    'first_name' => $this->complainer->first_name,
                    'last_name' => $this->complainer->last_name,
                    'email' => $this->complainer->email,
                    'phone_number' => $this->complainer->phone_number,
                    'address' => $this->complainer->address,
                ] : ['first_name' => 'Anonymous', 'last_name' => null, 'email' => null, 'phone_number' => null, 'address' => null];
            }),
            'org_classification_label' => $this->whenLoaded('orgClassification', fn () => $this->orgClassification?->label),
            'programme_name' => $this->whenLoaded('programme', fn () => $this->programme?->name),
            'district_name' => $this->whenLoaded('district', fn () => $this->district?->name),
            'days_open' => $sla['days_open'],
            'sla_days' => $sla['sla_days'],
            'sla_status' => $sla['status'],
            'suspects_count' => $this->when(isset($this->suspects_count), fn () => $this->suspects_count),
            'suspects' => $this->whenLoaded('suspects', fn () => $this->suspects->map(fn ($s) => [
                'id' => $s->id,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'title' => $s->title,
                'phone_number' => $s->phone_number,
                'email' => $s->email,
                'is_beneficiary' => (bool) $s->is_beneficiary,
                'beneficiary_id_number' => $s->beneficiary_id_number,
                'programme' => $s->programme ? ['id' => $s->programme->id, 'name' => $s->programme->name] : null,
                'implementing_organization' => $s->implementingOrganization ? [
                    'id' => $s->implementingOrganization->id,
                    'name' => $s->implementingOrganization->name,
                ] : null,
            ])),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'from' => $h->from_state?->value,
                'to' => $h->to_state->value,
                'note' => $h->note,
                'occurred_at' => $h->occurred_at->toIso8601String(),
            ])),
        ];
    }
}
