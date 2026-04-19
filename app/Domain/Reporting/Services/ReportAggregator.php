<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cached read-model for dashboard + reports. Each method accepts an optional
 * $orgId to scope results to a single organization (null = system-wide).
 * Cache keys include the org scope so per-org and global results are cached
 * independently.
 */
class ReportAggregator
{
    private const TTL_SECONDS = 300;

    /** @return array<string, int> */
    public function stateCounts(?int $orgId = null): array
    {
        return Cache::remember($this->key('state_counts', $orgId), self::TTL_SECONDS, function () use ($orgId): array {
            $rows = $this->scoped(Grievance::query(), $orgId)
                ->select('state', DB::raw('count(*) as total'))
                ->groupBy('state')
                ->pluck('total', 'state')
                ->toArray();

            foreach (GrievanceState::cases() as $state) {
                $rows[$state->value] ??= 0;
            }

            return $rows;
        });
    }

    /** @return array{days:int, series:list<array{date:string,count:int}>} */
    public function submissionsPerDay(int $days = 30, ?int $orgId = null): array
    {
        return Cache::remember($this->key("submissions_per_day:{$days}", $orgId), self::TTL_SECONDS, function () use ($days, $orgId): array {
            $series = $this->buildSubmissionSeries($days, $orgId);
            $total = array_sum(array_column($series, 'count'));

            if ($total === 0 && $days < 90) {
                return ['days' => 90, 'series' => $this->buildSubmissionSeries(90, $orgId)];
            }

            return ['days' => $days, 'series' => $series];
        });
    }

    /** @return list<array{date:string,count:int}> */
    private function buildSubmissionSeries(int $days, ?int $orgId): array
    {
        $start = now()->startOfDay()->subDays($days - 1);

        $byDate = $this->scoped(Grievance::query(), $orgId)
            ->where('received_at', '>=', $start)
            ->selectRaw('DATE(received_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $date, 'count' => (int) ($byDate[$date] ?? 0)];
        }

        return $out;
    }

    public function averageResolutionDays(?Carbon $since = null, ?int $orgId = null): ?float
    {
        $since ??= now()->subDays(90);

        return Cache::remember($this->key("avg_resolution:{$since->toDateString()}", $orgId), self::TTL_SECONDS, function () use ($since, $orgId): ?float {
            $avg = $this->scoped(Grievance::query(), $orgId)
                ->whereNotNull('resolved_at')
                ->where('received_at', '>=', $since)
                ->whereRaw('julianday(resolved_at) >= julianday(received_at)')
                ->selectRaw('AVG(julianday(resolved_at) - julianday(received_at)) as d')
                ->value('d');

            return $avg === null ? null : round((float) $avg, 1);
        });
    }

    private const SLA_DAYS = 90;
    private const APPROACH_DAYS = 60;

    public function resolutionRate(?int $orgId = null): ?float
    {
        return Cache::remember($this->key('resolution_rate', $orgId), self::TTL_SECONDS, function () use ($orgId): ?float {
            $total = $this->scoped(Grievance::query(), $orgId)
                ->whereNotIn('state', [GrievanceState::Rejected->value, GrievanceState::Trashed->value])
                ->count();

            if ($total === 0) {
                return null;
            }

            $within = $this->scoped(Grievance::query(), $orgId)
                ->whereNotNull('resolved_at')
                ->whereRaw('julianday(resolved_at) >= julianday(received_at)')
                ->whereRaw('CAST(julianday(resolved_at) - julianday(received_at) AS INTEGER) <= '.self::SLA_DAYS)
                ->count();

            return round(($within / $total) * 100, 1);
        });
    }

    /** @return array{within:int, approaching:int, breached:int} */
    public function slaBuckets(?int $orgId = null): array
    {
        return Cache::remember($this->key('sla_buckets', $orgId), self::TTL_SECONDS, function () use ($orgId): array {
            $activeStates = [
                GrievanceState::Submitted->value,
                GrievanceState::UnderReview->value,
                GrievanceState::Accepted->value,
                GrievanceState::Categorized->value,
                GrievanceState::Assigned->value,
                GrievanceState::OrgClassified->value,
                GrievanceState::InProgress->value,
                GrievanceState::Escalated->value,
                GrievanceState::UnderAdminReview->value,
                GrievanceState::Reopened->value,
            ];

            $rows = $this->scoped(Grievance::query(), $orgId)
                ->whereIn('state', $activeStates)
                ->selectRaw("
                    SUM(CASE WHEN CAST(julianday('now') - julianday(received_at) AS INTEGER) <= ".self::APPROACH_DAYS.' THEN 1 ELSE 0 END) AS within,
                    SUM(CASE WHEN CAST(julianday(\'now\') - julianday(received_at) AS INTEGER) > '.self::APPROACH_DAYS.' AND CAST(julianday(\'now\') - julianday(received_at) AS INTEGER) <= '.self::SLA_DAYS.' THEN 1 ELSE 0 END) AS approaching,
                    SUM(CASE WHEN CAST(julianday(\'now\') - julianday(received_at) AS INTEGER) > '.self::SLA_DAYS.' THEN 1 ELSE 0 END) AS breached
                ')
                ->first();

            return [
                'within' => (int) ($rows->within ?? 0),
                'approaching' => (int) ($rows->approaching ?? 0),
                'breached' => (int) ($rows->breached ?? 0),
            ];
        });
    }

    public function slaBreaches(?int $orgId = null): int
    {
        return Cache::remember($this->key('sla_breaches', $orgId), self::TTL_SECONDS, function () use ($orgId): int {
            return $this->scoped(Grievance::query(), $orgId)
                ->join('priority', 'priority.id', '=', 'grievance.priority_id')
                ->whereIn('grievance.state', [
                    GrievanceState::Submitted->value,
                    GrievanceState::UnderReview->value,
                    GrievanceState::InProgress->value,
                ])
                ->whereNotNull('priority.resolution_time_hours')
                ->whereRaw("(julianday('now') - julianday(grievance.received_at)) * 24 > priority.resolution_time_hours")
                ->count();
        });
    }

    /** @return list<array{label: string, count: int}> */
    public function byRegion(?int $orgId = null): array
    {
        return Cache::remember($this->key('by_region', $orgId), self::TTL_SECONDS, function () use ($orgId): array {
            return $this->scoped(Grievance::query(), $orgId)
                ->leftJoin('region', 'region.id', '=', 'grievance.region_id')
                ->selectRaw("COALESCE(region.name, 'Unknown') as label, count(*) as count")
                ->groupBy('label')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count])
                ->all();
        });
    }

    /** @return list<array{label: string, count: int}> */
    public function byGrievanceType(?int $orgId = null): array
    {
        return Cache::remember($this->key('by_type', $orgId), self::TTL_SECONDS, function () use ($orgId): array {
            return $this->scoped(Grievance::query(), $orgId)
                ->join('grievance_type', 'grievance_type.id', '=', 'grievance.grievance_type_id')
                ->selectRaw('grievance_type.name as label, count(*) as count')
                ->groupBy('grievance_type.id', 'grievance_type.name')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count])
                ->all();
        });
    }

    public function flush(): void
    {
        Cache::forget($this->key('state_counts'));
        Cache::forget($this->key('sla_breaches'));
        Cache::forget($this->key('by_region'));
        Cache::forget($this->key('by_type'));
    }

    /**
     * Scope a query builder to a single org when set, or leave system-wide
     * when null.
     */
    private function scoped(Builder $query, ?int $orgId): Builder
    {
        if ($orgId !== null) {
            $query->where('grievance.classified_organization_id', $orgId);
        }

        return $query;
    }

    private function key(string $base, ?int $orgId = null): string
    {
        return $orgId === null
            ? "reports:{$base}:global"
            : "reports:{$base}:org_{$orgId}";
    }
}
