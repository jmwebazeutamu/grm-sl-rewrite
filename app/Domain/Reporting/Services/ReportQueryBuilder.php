<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReportQueryBuilder
{
    private const FIELD_MAP = [
        'ref' => 'grievance.g_number',
        'summary' => 'grievance.summary',
        'status' => 'grievance.state',
        'is_anonymous' => 'grievance.is_anonymous',
        'submitted_at' => 'grievance.created_at',
        'received_at' => 'grievance.received_at',
        'resolved_at' => 'grievance.resolved_at',
        'closed_at' => 'grievance.closed_at',
        'grievance_type' => 'gt.name',
        'org_classification' => 'ogt.label',
        'programme' => 'prog.name',
        'region' => 'r.name',
        'district' => 'd.name',
        'chiefdom' => 'ch.name',
        'implementing_org' => 'io.name',
        'assigned_org' => 'co.name',
        'assigned_officer' => 'officer.name',
        'complainant_name' => "comp.first_name || ' ' || comp.last_name",
    ];

    private const JOIN_MAP = [
        'grievance_type' => ['gt', 'grievance_type', 'gt.id', 'grievance.grievance_type_id'],
        'org_classification' => ['ogt', 'org_grievance_types', 'ogt.id', 'grievance.org_classification_id'],
        'programme' => ['prog', 'programme', 'prog.id', 'grievance.programme_id'],
        'region' => ['r', 'region', 'r.id', 'grievance.region_id'],
        'district' => ['d', 'district', 'd.id', 'grievance.district_id'],
        'chiefdom' => ['ch', 'chiefdom', 'ch.id', 'grievance.chiefdom_id'],
        'assigned_org' => ['co', 'organization', 'co.id', 'grievance.classified_organization_id'],
        'implementing_org' => ['io', 'organization', 'io.id', 'grievance.implementing_organization_id'],
        'assigned_officer' => ['officer', 'person', 'officer.id', 'grievance.assigned_officer_id'],
        'complainant_name' => ['comp', 'grievance_complainer', 'comp.grievance_id', 'grievance.id'],
    ];

    private const FILTER_FIELD_TO_JOIN = [
        'district' => 'district',
        'programme' => 'programme',
        'assigned_org' => 'assigned_org',
        'grievance_type' => 'grievance_type',
    ];

    private const FILTER_FIELD_TO_COLUMN = [
        'submitted_at' => 'grievance.created_at',
        'district' => 'grievance.district_id',
        'status' => 'grievance.state',
        'summary' => 'grievance.summary',
        'programme' => 'grievance.programme_id',
        'assigned_org' => 'grievance.classified_organization_id',
        'grievance_type' => 'grievance.grievance_type_id',
    ];

    public const VALID_FIELDS = [
        'ref', 'summary', 'status', 'grievance_type', 'org_classification',
        'programme', 'region', 'district', 'chiefdom', 'implementing_org',
        'assigned_org', 'assigned_officer', 'days_open', 'days_to_resolution',
        'submitted_at', 'received_at', 'resolved_at', 'closed_at', 'is_anonymous',
        'complainant_name',
    ];

    public const FIELD_LABELS = [
        'ref' => 'Reference number',
        'summary' => 'Summary',
        'status' => 'Status',
        'grievance_type' => 'Grievance type',
        'org_classification' => 'Org sub-classification',
        'programme' => 'Programme',
        'region' => 'Region',
        'district' => 'District',
        'chiefdom' => 'Chiefdom',
        'implementing_org' => 'Implementing organisation',
        'assigned_org' => 'Assigned organisation',
        'assigned_officer' => 'Assigned officer',
        'days_open' => 'Days in system',
        'days_to_resolution' => 'Days to resolution',
        'submitted_at' => 'Registration date',
        'received_at' => 'Received date',
        'resolved_at' => 'Resolution date',
        'closed_at' => 'Closure date',
        'is_anonymous' => 'Anonymous submission',
        'complainant_name' => 'Complainant name',
    ];

    /**
     * @param  list<string>  $fields
     * @param  list<array{field: string, operator: string, value: mixed}>  $filters
     */
    public function build(array $fields, array $filters, ?User $user): Builder
    {
        $query = Grievance::query();
        $query->select(['grievance.id']);
        $joined = [];

        foreach ($fields as $f) {
            if ($f === 'days_open') {
                $query->selectRaw("CAST(julianday(COALESCE(grievance.resolved_at, grievance.closed_at, datetime('now'))) - julianday(grievance.created_at) AS INTEGER) as days_open");

                continue;
            }
            if ($f === 'days_to_resolution') {
                $query->selectRaw('CAST(julianday(grievance.resolved_at) - julianday(grievance.created_at) AS INTEGER) as days_to_resolution');

                continue;
            }

            $col = self::FIELD_MAP[$f] ?? null;
            if ($col === null) {
                continue;
            }

            if (isset(self::JOIN_MAP[$f]) && ! isset($joined[$f])) {
                [$alias, $table, $left, $right] = self::JOIN_MAP[$f];
                $query->leftJoin("{$table} as {$alias}", $left, '=', $right);
                $joined[$f] = true;
            }

            $query->addSelect(\DB::raw("{$col} as {$f}"));
        }

        foreach ($filters as $filter) {
            $fField = $filter['field'] ?? '';
            $op = $filter['operator'] ?? '';
            $val = $filter['value'] ?? null;

            $joinKey = self::FILTER_FIELD_TO_JOIN[$fField] ?? null;
            if ($joinKey && ! isset($joined[$joinKey])) {
                [$alias, $table, $left, $right] = self::JOIN_MAP[$joinKey];
                $query->leftJoin("{$table} as {$alias}", $left, '=', $right);
                $joined[$joinKey] = true;
            }

            $this->applyFilter($query, $fField, $op, $val);
        }

        if ($user && $user->organization_id !== null
            && ! $user->hasRole('super-admin')
            && ! $user->hasRole('acc-reviewer')
            && ! $user->hasRole('grm-data-operator')
        ) {
            $query->where('grievance.classified_organization_id', $user->organization_id);
        }

        return $query->orderByDesc('grievance.created_at');
    }

    private function applyFilter(Builder $query, string $field, string $op, mixed $value): void
    {
        if ($field === 'days_open' || $field === 'days_to_resolution') {
            $expr = $field === 'days_open'
                ? "CAST(julianday(COALESCE(grievance.resolved_at, grievance.closed_at, datetime('now'))) - julianday(grievance.created_at) AS INTEGER)"
                : 'CAST(julianday(grievance.resolved_at) - julianday(grievance.created_at) AS INTEGER)';

            if ($field === 'days_to_resolution') {
                $query->whereNotNull('grievance.resolved_at');
            }

            if ($op === 'between' && is_array($value) && count($value) === 2) {
                $query->whereRaw("{$expr} BETWEEN ? AND ?", [(int) $value[0], (int) $value[1]]);
            } else {
                $query->whereRaw("{$expr} {$op} ?", [(int) $value]);
            }

            return;
        }

        $col = self::FILTER_FIELD_TO_COLUMN[$field] ?? null;
        if ($col === null) {
            return;
        }

        if ($op === 'contains') {
            $query->where($col, 'like', "%{$value}%");

            return;
        }

        if ($op === 'in') {
            $values = is_array($value) ? $value : explode(',', (string) $value);
            $query->whereIn($col, $values);

            return;
        }

        if ($op === 'between' && is_array($value) && count($value) === 2) {
            $query->whereBetween($col, $value);

            return;
        }

        $query->where($col, $op, $value);
    }
}
