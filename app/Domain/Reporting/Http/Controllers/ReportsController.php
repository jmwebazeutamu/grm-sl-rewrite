<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Locality\Models\District;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reporting\Http\Requests\ReportRequest;
use App\Domain\Reporting\Models\SavedReport;
use App\Domain\Reporting\Services\ReportAggregator;
use App\Domain\Reporting\Services\ReportQueryBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $savedQuery = SavedReport::query()->orderBy('name');

        if (! $user->hasRole('super-admin') && $user->organization_id !== null) {
            $savedQuery->where('organization_id', $user->organization_id);
        }

        return Inertia::render('Reports/Index', [
            'districts' => District::orderBy('name')->get(['id', 'name']),
            'programmes' => Programme::active()->orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'grievanceTypes' => GrievanceType::orderBy('name')->get(['id', 'name']),
            'states' => collect(GrievanceState::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'savedReports' => $savedQuery->get(['id', 'name', 'fields', 'filters']),
            'fieldLabels' => ReportQueryBuilder::FIELD_LABELS,
            'validFields' => ReportQueryBuilder::VALID_FIELDS,
        ]);
    }

    public function preview(ReportRequest $request, ReportQueryBuilder $builder): JsonResponse
    {
        $data = $request->validated();
        $query = $builder->build($data['fields'], $data['filters'] ?? [], $request->user());
        $total = (clone $query)->count();
        $rows = $query->limit(200)->get();

        return response()->json([
            'rows' => $rows,
            'total' => $total,
            'fields' => $data['fields'],
        ]);
    }

    public function export(ReportRequest $request, ReportQueryBuilder $builder): StreamedResponse
    {
        $data = $request->validated();
        $fields = $data['fields'];
        $query = $builder->build($fields, $data['filters'] ?? [], $request->user());

        $labels = ReportQueryBuilder::FIELD_LABELS;
        $headerRow = array_map(fn ($f) => $labels[$f] ?? $f, $fields);

        return response()->stream(
            function () use ($query, $fields, $headerRow): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, $headerRow);

                $query->chunk(500, function ($rows) use ($out, $fields): void {
                    foreach ($rows as $row) {
                        $csvRow = [];
                        foreach ($fields as $f) {
                            $csvRow[] = $row->{$f} ?? '';
                        }
                        fputcsv($out, $csvRow);
                    }
                });

                fclose($out);
            },
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="grievances-report-'.date('Y-m-d').'.csv"',
            ],
        );
    }

    public function saveReport(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'fields' => ['required', 'array', 'min:1'],
            'filters' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        SavedReport::create([
            'name' => $data['name'],
            'created_by_id' => $user->id,
            'organization_id' => $user->organization_id,
            'fields' => $data['fields'],
            'filters' => $data['filters'] ?? [],
        ]);

        return back()->with('success', 'Report saved.');
    }

    public function destroySavedReport(SavedReport $report): RedirectResponse
    {
        $user = request()->user();

        if (! $user->hasRole('super-admin') && $user->organization_id !== $report->organization_id) {
            abort(403);
        }

        $report->delete();

        return back()->with('success', 'Saved report deleted.');
    }

    public function dashboard(Request $request, ReportAggregator $reports): Response
    {
        $user = $request->user();
        $orgId = $user?->organization_id;

        $myAssigned = 0;
        if ($user && $user->organization_id !== null) {
            $myAssigned = Grievance::where('assigned_officer_id', $user->id)
                ->whereNotIn('state', ['closed', 'rejected', 'trashed'])
                ->count();
        }

        $organization = $user && $user->organization_id
            ? \App\Domain\Organization\Models\Organization::find($user->organization_id)
            : null;

        return Inertia::render('Dashboard', [
            'widgets' => [
                'state_counts' => $reports->stateCounts($orgId),
                'submissions_per_day' => $reports->submissionsPerDay(30, $orgId),
                'resolution_rate' => $reports->resolutionRate($orgId),
                'sla_buckets' => $reports->slaBuckets($orgId),
                'sla_days' => $organization?->sla_days ?? 90,
            ],
            'organization' => $organization ? ['id' => $organization->id, 'name' => $organization->name] : null,
            'my_assigned' => $myAssigned,
        ]);
    }
}
