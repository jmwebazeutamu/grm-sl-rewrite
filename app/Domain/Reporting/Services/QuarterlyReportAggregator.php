<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Grievance\Models\Grievance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuarterlyReportAggregator
{
    private const TTL = 300;
    private const SLA_DAYS = 90;

    public function aggregate(int $year, ?int $organizationId = null): array
    {
        $key = "quarterly-report:{$organizationId}:{$year}";

        return Cache::remember($key, self::TTL, fn () => [
            'overall' => $this->overall($year, $organizationId),
            'by_programme' => $this->byProgramme($year, $organizationId),
            'by_classification' => $this->byClassification($year, $organizationId),
            'available_years' => $this->availableYears($organizationId),
        ]);
    }

    public function flush(int $year, ?int $organizationId): void
    {
        Cache::forget("quarterly-report:{$organizationId}:{$year}");
        Cache::forget("quarterly-report::{$year}");
    }

    /** @return list<array<string, mixed>> */
    private function overall(int $year, ?int $orgId): array
    {
        $rows = $this->baseQuery($year, $orgId)
            ->selectRaw($this->quarterExpr().' AS quarter')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM('.$this->resolvedExpr().') AS resolved_within_sla')
            ->selectRaw('SUM('.$this->breachedExpr().') AS breached')
            ->selectRaw('SUM('.$this->withinWindowExpr().') AS within_window')
            ->groupByRaw('quarter')
            ->get();

        return $this->fillQuarters($rows, $year);
    }

    /** @return list<array{name: string, data: list<array<string, mixed>>}> */
    private function byProgramme(int $year, ?int $orgId): array
    {
        // Use classified_programme_id: set at classification time. The separate
        // programme_id column holds the implementing programme assigned later,
        // which is rarely populated for legacy grievances.
        $rows = $this->baseQuery($year, $orgId)
            ->leftJoin('programme as prog', 'prog.id', '=', 'grievance.classified_programme_id')
            ->selectRaw($this->quarterExpr().' AS quarter')
            ->selectRaw("COALESCE(prog.name, 'Unlinked') AS programme_name")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM('.$this->resolvedExpr().') AS resolved_within_sla')
            ->selectRaw('SUM('.$this->breachedExpr().') AS breached')
            ->selectRaw('SUM('.$this->withinWindowExpr().') AS within_window')
            ->groupByRaw('quarter, programme_name')
            ->get();

        return $this->groupSeries($rows, 'programme_name', $year);
    }

    /** @return list<array{name: string, data: list<array<string, mixed>>}> */
    private function byClassification(int $year, ?int $orgId): array
    {
        // Prefer org_classification_id (per-org sub-classification) when present,
        // else fall back to the top-level grievance_type name.
        $rows = $this->baseQuery($year, $orgId)
            ->leftJoin('org_grievance_types as ogt', 'ogt.id', '=', 'grievance.org_classification_id')
            ->leftJoin('grievance_type as gt', 'gt.id', '=', 'grievance.grievance_type_id')
            ->selectRaw($this->quarterExpr().' AS quarter')
            ->selectRaw("COALESCE(ogt.label, gt.name, 'Unlinked') AS classification_name")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM('.$this->resolvedExpr().') AS resolved_within_sla')
            ->selectRaw('SUM('.$this->breachedExpr().') AS breached')
            ->selectRaw('SUM('.$this->withinWindowExpr().') AS within_window')
            ->groupByRaw('quarter, classification_name')
            ->get();

        return $this->groupSeries($rows, 'classification_name', $year);
    }

    /** @return list<int> */
    private function availableYears(?int $orgId): array
    {
        $q = Grievance::query()
            ->selectRaw("DISTINCT CAST(strftime('%Y', grievance.created_at) AS INTEGER) AS yr")
            ->whereNull('grievance.deleted_at')
            ->orderByDesc('yr');

        if ($orgId) {
            $q->where('classified_organization_id', $orgId);
        }

        return $q->pluck('yr')->map(fn ($v) => (int) $v)->values()->all();
    }

    private function baseQuery(int $year, ?int $orgId): \Illuminate\Database\Eloquent\Builder
    {
        $q = Grievance::query()
            ->whereRaw("strftime('%Y', grievance.created_at) = ?", [(string) $year])
            ->whereNull('grievance.deleted_at');

        if ($orgId) {
            $q->where('classified_organization_id', $orgId);
        }

        return $q;
    }

    private function quarterExpr(): string
    {
        return "CASE
            WHEN CAST(strftime('%m', grievance.created_at) AS INTEGER) BETWEEN 1 AND 3  THEN 'Q1'
            WHEN CAST(strftime('%m', grievance.created_at) AS INTEGER) BETWEEN 4 AND 6  THEN 'Q2'
            WHEN CAST(strftime('%m', grievance.created_at) AS INTEGER) BETWEEN 7 AND 9  THEN 'Q3'
            ELSE 'Q4'
        END";
    }

    private function resolvedExpr(): string
    {
        return 'CASE WHEN grievance.resolved_at IS NOT NULL
            AND CAST(julianday(grievance.resolved_at) - julianday(grievance.created_at) AS INTEGER) <= '.self::SLA_DAYS.'
            THEN 1 ELSE 0 END';
    }

    /**
     * Genuine failures: not resolved within 90 days AND the 90-day window
     * has already expired (so we can say so with confidence).
     */
    private function breachedExpr(): string
    {
        return "CASE WHEN (
                grievance.resolved_at IS NULL
                OR CAST(julianday(grievance.resolved_at) - julianday(grievance.created_at) AS INTEGER) > ".self::SLA_DAYS.'
            )
            AND CAST(julianday(\'now\') - julianday(grievance.created_at) AS INTEGER) > '.self::SLA_DAYS.'
            THEN 1 ELSE 0 END';
    }

    /**
     * Still open and the 90-day window has not expired yet — neither a
     * success nor a failure yet. Shown separately so recent grievances are
     * not prematurely marked "late".
     */
    private function withinWindowExpr(): string
    {
        return "CASE WHEN grievance.resolved_at IS NULL
            AND CAST(julianday('now') - julianday(grievance.created_at) AS INTEGER) <= ".self::SLA_DAYS.'
            THEN 1 ELSE 0 END';
    }

    /** @return list<array<string, mixed>> */
    private function fillQuarters(Collection $rows, int $year): array
    {
        $qDates = [
            'Q1' => ['Jan 1', 'Mar 31'],
            'Q2' => ['Apr 1', 'Jun 30'],
            'Q3' => ['Jul 1', 'Sep 30'],
            'Q4' => ['Oct 1', 'Dec 31'],
        ];

        $result = [];
        foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q) {
            $row = $rows->firstWhere('quarter', $q);
            $total = (int) ($row?->total ?? 0);
            $resolved = (int) ($row?->resolved_within_sla ?? 0);
            $breached = (int) ($row?->breached ?? 0);
            $withinWindow = (int) ($row?->within_window ?? 0);
            $rate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;
            $result[] = [
                'year' => $year,
                'quarter' => $q,
                'quarter_label' => "{$q} {$year}",
                'date_range' => "{$qDates[$q][0]} – {$qDates[$q][1]}, {$year}",
                'total' => $total,
                'resolved_within_sla' => $resolved,
                'breached' => $breached,
                'within_window' => $withinWindow,
                // Kept for backwards compatibility with any CSV export or
                // consumer still expecting the old key.
                'unresolved_or_late' => $breached,
                'resolution_rate' => $rate,
            ];
        }

        return $result;
    }

    /** @return list<array{name: string, data: list<array<string, mixed>>}> */
    private function groupSeries(Collection $rows, string $nameCol, int $year): array
    {
        $grouped = $rows->groupBy($nameCol);
        $series = [];

        foreach ($grouped as $name => $group) {
            $series[] = [
                'name' => (string) $name,
                'data' => $this->fillQuarters($group, $year),
            ];
        }

        usort($series, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $series;
    }
}
