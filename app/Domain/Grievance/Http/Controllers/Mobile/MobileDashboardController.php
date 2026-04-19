<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Mobile;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Reporting\Services\ReportAggregator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    public function index(Request $request, ReportAggregator $reports): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $states = $reports->stateCounts($orgId);
        $active = collect(GrievanceState::activeStates())
            ->sum(fn ($s) => $states[$s->value] ?? 0);

        $sla = $reports->slaBuckets($orgId);

        $resolvedThisMonth = Grievance::query()
            ->when($orgId, fn ($q, $id) => $q->where('classified_organization_id', $id))
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->startOfMonth())
            ->count();

        $myAssigned = Grievance::where('assigned_officer_id', $user->id)
            ->whereNotIn('state', ['closed', 'rejected', 'trashed'])
            ->count();

        $recent = Grievance::query()
            ->when($orgId, fn ($q, $id) => $q->where('classified_organization_id', $id))
            ->with('grievanceType:id,name')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'g_number', 'summary', 'state', 'updated_at', 'grievance_type_id'])
            ->map(fn ($g) => [
                'id' => $g->id,
                'g_number' => $g->g_number,
                'summary' => $g->summary,
                'state' => $g->state->value,
                'state_label' => $g->state->label(),
                'updated_at' => $g->updated_at?->toIso8601String(),
            ])
            ->values();

        $submissions = $reports->submissionsPerDay(30, $orgId);

        return response()->json([
            'active_cases' => $active,
            'sla_breached' => $sla['breached'],
            'sla_approaching' => $sla['approaching'],
            'sla_within' => $sla['within'],
            'resolved_this_month' => $resolvedThisMonth,
            'closed' => $states[GrievanceState::Closed->value] ?? 0,
            'resolution_rate' => $reports->resolutionRate($orgId),
            'my_assigned' => $myAssigned,
            'by_state' => $states,
            'submissions' => $submissions,
            'recent_activity' => $recent,
        ]);
    }
}
