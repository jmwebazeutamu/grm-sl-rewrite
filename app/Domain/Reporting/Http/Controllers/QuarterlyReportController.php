<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Organization\Models\Organization;
use App\Domain\Reporting\Services\QuarterlyReportAggregator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuarterlyReportController extends Controller
{
    public function __construct(private readonly QuarterlyReportAggregator $aggregator)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isGlobal = $user->hasAnyRole(['super-admin', 'acc-reviewer', 'grm-data-operator']);
        $orgId = $isGlobal ? null : $user->organization_id;
        $year = (int) $request->query('year', (string) now()->year);

        return Inertia::render('Reports/Quarterly', [
            'reportData' => $this->aggregator->aggregate($year, $orgId),
            'selectedYear' => $year,
            'isGlobalView' => $isGlobal,
            'organizations' => $isGlobal ? Organization::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function fetch(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobal = $user->hasAnyRole(['super-admin', 'acc-reviewer', 'grm-data-operator']);
        $year = (int) $request->query('year', (string) now()->year);

        if ($isGlobal) {
            $orgId = $request->query('organization_id') ? (int) $request->query('organization_id') : null;
        } else {
            $orgId = $user->organization_id;
        }

        return response()->json($this->aggregator->aggregate($year, $orgId));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $isGlobal = $user->hasAnyRole(['super-admin', 'acc-reviewer', 'grm-data-operator']);
        $year = (int) $request->input('year', (string) now()->year);
        $orgId = $isGlobal ? ($request->input('organization_id') ? (int) $request->input('organization_id') : null) : $user->organization_id;

        $data = $this->aggregator->aggregate($year, $orgId);

        return response()->stream(
            function () use ($data): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Quarter', 'Date range', 'Registered', 'Resolved ≤90 days', 'Unresolved/Late', 'Resolution rate %']);

                foreach ($data['overall'] as $row) {
                    fputcsv($out, [
                        $row['quarter_label'],
                        $row['date_range'],
                        $row['total'],
                        $row['resolved_within_sla'],
                        $row['unresolved_or_late'],
                        $row['resolution_rate'],
                    ]);
                }

                fclose($out);
            },
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"quarterly-report-{$year}.csv\"",
            ],
        );
    }
}
