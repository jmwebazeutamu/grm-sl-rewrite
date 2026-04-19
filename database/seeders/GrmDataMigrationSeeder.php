<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\FeedbackRating;
use App\Domain\Grievance\Enums\GrievanceState;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GrmDataMigrationSeeder extends Seeder
{
    private const COL_REF = 0;
    private const COL_STATUS = 1;
    private const COL_SUMMARY = 2;
    private const COL_DESCRIPTION = 3;
    private const COL_RECEIVED = 4;
    private const COL_HOW_REPORTED = 5;
    private const COL_REGION = 6;
    private const COL_DISTRICT = 7;
    private const COL_CHIEFDOM = 8;
    private const COL_SECTION = 9;
    private const COL_LOCALITY = 10;
    private const COL_REVIEW_OUTCOME = 11;
    private const COL_BENEFICIARY = 12;
    private const COL_COMPLAINERS = 13;
    private const COL_ORG = 14;
    private const COL_ORG_SUBCLASS = 15;
    private const COL_REMARK_1 = 16;
    private const COL_PROGRAMME = 17;
    private const COL_ASSIGNMENT_TEXT = 19;
    private const COL_ACTION_TYPE = 20;
    private const COL_ASSIGNED_STAFF = 21;
    private const COL_ACTION_CREATED = 23;
    private const COL_ACTION_CREATED_BY = 25;
    private const COL_REMARKS_3 = 27;
    private const COL_REMARKS_DATE = 30;
    private const COL_REMARKS_CREATED_BY = 32;
    private const COL_RESOLUTION_SUMMARY = 34;
    private const COL_RESOLUTION_DATE = 36;
    private const COL_RESOLUTION_CREATED_BY = 37;
    private const COL_FEEDBACK_COMMENTS = 38;
    private const COL_FEEDBACK_STATUS = 39;
    private const COL_FEEDBACK_SATISFACTION = 40;
    private const COL_FEEDBACK_CONTACTED_ON = 41;
    private const COL_FEEDBACK_CONTACTED_BY_NAME = 43;
    private const COL_CLOSURE_DATE = 44;

    /** @var array<string, array<string, int>> */
    private array $refs = [];

    /** @var array<string, int> */
    private array $errors = [];

    public function run(): void
    {
        $path = database_path('seeders/grm_data2.csv');

        if (! file_exists($path)) {
            $this->command->error("grm_data2.csv not found at {$path}");

            return;
        }

        $this->loadReferences();

        $rows = $this->readCsv($path);
        $this->command->info('Loaded '.count($rows).' rows from CSV.');

        $done = $skipped = $failed = 0;

        DB::transaction(function () use ($rows, &$done, &$skipped, &$failed) {
            foreach ($rows as $row) {
                $ref = trim($row[self::COL_REF] ?? '');

                if ($ref === '') {
                    $skipped++;

                    continue;
                }

                if (DB::table('grievance')->where('g_number', $ref)->exists()) {
                    $skipped++;

                    continue;
                }

                try {
                    $this->importRow($row, $ref);
                    $done++;

                    if ($done % 100 === 0) {
                        $this->command->info("  imported {$done}...");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->logError("Row {$ref}: ".$e->getMessage());
                }
            }
        });

        $this->command->info('=== MIGRATION COMPLETE ===');
        $this->command->info("Imported:  {$done}");
        $this->command->info("Skipped:   {$skipped}");
        $this->command->info("Failed:    {$failed}");
        $this->command->info('Warnings:  '.count($this->errors).' (unique messages, with counts)');

        foreach ($this->errors as $msg => $count) {
            $this->command->warn("  [{$count}×] {$msg}");
        }
    }

    /** @return list<list<string>> */
    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        fgetcsv($fh); // header
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            $rows[] = $r;
        }
        fclose($fh);

        return $rows;
    }

    private function loadReferences(): void
    {
        // District aliases used only in the CSV.
        $districtAliases = ['kambia 1' => 'kambia', 'portloko' => 'port loko'];

        foreach (['region', 'district', 'chiefdom', 'section', 'locality'] as $t) {
            $this->refs[$t] = DB::table($t)->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])
                ->all();
        }

        foreach ($districtAliases as $alias => $real) {
            if (isset($this->refs['district'][$real])) {
                $this->refs['district'][$alias] = $this->refs['district'][$real];
            }
        }

        $this->refs['organization'] = [];
        foreach (DB::table('organization')->get(['id', 'name', 'acronym']) as $o) {
            $this->refs['organization'][strtolower(trim($o->name))] = $o->id;
            if ($o->acronym) {
                $this->refs['organization'][strtolower(trim($o->acronym))] = $o->id;
            }
        }

        $this->refs['organization']['nacsa'] = $this->refs['organization']['nacsa']
            ?? ($this->refs['organization']['national commission for social action'] ?? null);

        $this->refs['programme'] = DB::table('programme')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->all();

        $this->refs['org_type'] = [];
        foreach (DB::table('org_grievance_types')->get(['id', 'label', 'organization_id']) as $r) {
            $key = $r->organization_id.'|'.strtolower(trim($r->label));
            $this->refs['org_type'][$key] = $r->id;
        }

        $this->refs['how_reported'] = DB::table('how_reported')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->all();

        $this->refs['grievance_type'] = DB::table('grievance_type')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->all();

        $this->refs['user'] = DB::table('person')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->all();
    }

    /** @param list<string> $row */
    private function importRow(array $row, string $ref): void
    {
        $receivedAt = $this->parseDate($row[self::COL_RECEIVED] ?? null) ?? now();
        $closedAt = $this->parseDate($row[self::COL_CLOSURE_DATE] ?? null);
        $resolvedAt = $this->parseDate($row[self::COL_RESOLUTION_DATE] ?? null);

        $stateRaw = trim($row[self::COL_STATUS] ?? '');
        $state = $this->mapState($stateRaw);

        $reviewOutcome = strtoupper(trim($row[self::COL_REVIEW_OUTCOME] ?? ''));
        if ($reviewOutcome === 'REJECT') {
            $state = GrievanceState::Rejected->value;
        }

        $orgId = $this->lookup('organization', $row[self::COL_ORG] ?? null);
        $programmeId = $this->lookupProgramme($row[self::COL_PROGRAMME] ?? null);
        $howReportedId = $this->lookup('how_reported', $row[self::COL_HOW_REPORTED] ?? null);

        $orgTypeLabel = trim($row[self::COL_ORG_SUBCLASS] ?? '');
        $orgTypeId = null;
        if ($orgId && $orgTypeLabel !== '') {
            $key = $orgId.'|'.strtolower($orgTypeLabel);
            $orgTypeId = $this->refs['org_type'][$key] ?? null;
            if (! $orgTypeId) {
                $this->logError("unmatched org_grievance_type: org={$orgId} label=\"{$orgTypeLabel}\"");
            }
        }

        $grievanceTypeId = $this->mapGrievanceType($orgTypeLabel);

        $geo = $this->resolveGeography(
            $row[self::COL_REGION] ?? null,
            $row[self::COL_DISTRICT] ?? null,
            $row[self::COL_CHIEFDOM] ?? null,
            $row[self::COL_SECTION] ?? null,
            $row[self::COL_LOCALITY] ?? null,
        );

        $assignedOfficerId = $this->lookupUser($row[self::COL_ASSIGNED_STAFF] ?? null);

        $grievanceId = DB::table('grievance')->insertGetId([
            'g_number' => $ref,
            'summary' => $this->trim($row[self::COL_SUMMARY] ?? '', 'Legacy import'),
            'description' => $row[self::COL_DESCRIPTION] ?? null,
            'grievance_type_id' => $grievanceTypeId,
            'how_reported_id' => $howReportedId,
            'state' => $state,
            'is_anonymous' => 0,
            'region_id' => $geo['region_id'],
            'district_id' => $geo['district_id'],
            'chiefdom_id' => $geo['chiefdom_id'],
            'section_id' => $geo['section_id'],
            'locality_id' => $geo['locality_id'],
            'received_at' => $receivedAt,
            'resolved_at' => $resolvedAt,
            'closed_at' => $closedAt,
            'assigned_officer_id' => $assignedOfficerId,
            'classified_organization_id' => $orgId,
            'classified_programme_id' => $programmeId,
            'org_classification_id' => $orgTypeId,
            'programme_id' => $programmeId,
            'implementing_organization_id' => $orgId,
            'assigned_at' => $assignedOfficerId ? $receivedAt : null,
            'reviewed_at' => $reviewOutcome !== '' ? $receivedAt : null,
            'accepted_at' => $reviewOutcome === 'ACCEPT' ? $receivedAt : null,
            'created_at' => $receivedAt,
            'updated_at' => now(),
        ]);

        $this->insertComplainer($grievanceId, $row);
        $this->insertBeneficiary($grievanceId, $row);
        $this->insertActions($grievanceId, $row, $receivedAt);
        $this->insertFeedback($grievanceId, $row);
        $this->insertStatusHistory($grievanceId, $state, $receivedAt, $assignedOfficerId);
    }

    private function mapState(string $raw): string
    {
        return match (strtolower($raw)) {
            'submitted' => GrievanceState::Submitted->value,
            'in progress' => GrievanceState::InProgress->value,
            'resolved' => GrievanceState::Resolved->value,
            'closed' => GrievanceState::Closed->value,
            'rejected' => GrievanceState::Rejected->value,
            default => GrievanceState::Submitted->value,
        };
    }

    private function mapGrievanceType(string $orgSubclass): int
    {
        $unclassified = $this->refs['grievance_type']['unclassified'] ?? 37;
        $m = [
            'payments' => 'payments',
            'sim issues' => null,
            'household selection' => 'household selection',
            'other' => 'others',
            'corruption / bribery' => 'bribery',
            'missing data' => 'missing data',
            'administrative' => 'administrative',
            'identification' => 'lack of id document',
        ];
        $target = $m[strtolower($orgSubclass)] ?? null;

        if ($target === null) {
            return $unclassified;
        }

        return $this->refs['grievance_type'][$target] ?? $unclassified;
    }

    private function lookup(string $table, ?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $key = strtolower($name);
        $id = $this->refs[$table][$key] ?? null;
        if (! $id) {
            $this->logError("unmatched {$table}: \"{$name}\"");
        }

        return $id;
    }

    private function lookupProgramme(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $id = $this->refs['programme'][strtolower($name)] ?? null;
        if (! $id) {
            $this->logError("unmatched programme: \"{$name}\"");
        }

        return $id;
    }

    private function lookupUser(?string $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $name = trim(explode('|', $raw)[0]);
        $id = $this->refs['user'][strtolower($name)] ?? null;
        if (! $id) {
            $this->logError("unmatched user: \"{$name}\"");
        }

        return $id;
    }

    /** @return array{region_id:?int, district_id:?int, chiefdom_id:?int, section_id:?int, locality_id:?int} */
    private function resolveGeography(?string $region, ?string $district, ?string $chiefdom, ?string $section, ?string $locality): array
    {
        $clean = fn (?string $v) => strtolower(trim(rtrim((string) $v, ', ')));

        $regionId = $region ? ($this->refs['region'][$clean($region)] ?? null) : null;
        $districtId = $district ? ($this->refs['district'][$clean($district)] ?? null) : null;
        $chiefdomId = $chiefdom ? ($this->refs['chiefdom'][$clean($chiefdom)] ?? null) : null;
        $sectionId = $section ? ($this->refs['section'][$clean($section)] ?? null) : null;
        $localityId = $locality ? ($this->refs['locality'][$clean($locality)] ?? null) : null;

        foreach ([['region', $region, $regionId], ['district', $district, $districtId], ['chiefdom', $chiefdom, $chiefdomId]] as [$t, $v, $id]) {
            if ($v && trim($v) !== '' && ! $id) {
                $this->logError("unmatched {$t}: \"".trim($v).'"');
            }
        }

        return [
            'region_id' => $regionId,
            'district_id' => $districtId,
            'chiefdom_id' => $chiefdomId,
            'section_id' => $sectionId,
            'locality_id' => $localityId,
        ];
    }

    /** @param list<string> $row */
    private function insertComplainer(int $grievanceId, array $row): void
    {
        $raw = trim($row[self::COL_COMPLAINERS] ?? '');
        if ($raw === '') {
            return;
        }

        $first = explode('|', $raw)[0];
        [$fn, $ln] = $this->splitName($first);

        DB::table('grievance_complainer')->insert([
            'grievance_id' => $grievanceId,
            'first_name' => $fn,
            'last_name' => $ln,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param list<string> $row */
    private function insertBeneficiary(int $grievanceId, array $row): void
    {
        $raw = trim($row[self::COL_BENEFICIARY] ?? '');
        if ($raw === '') {
            return;
        }

        [$fn, $ln] = $this->splitName($raw);

        DB::table('grievance_suspect')->insert([
            'grievance_id' => $grievanceId,
            'first_name' => $fn,
            'last_name' => $ln,
            'is_beneficiary' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param list<string> $row */
    private function insertActions(int $grievanceId, array $row, Carbon $receivedAt): void
    {
        $actionType = $this->mapActionType($row[self::COL_ACTION_TYPE] ?? '');

        // First action: REMARK 1 + assignment text (combined into body)
        $remark1 = trim($row[self::COL_REMARK_1] ?? '');
        $assignment = trim($row[self::COL_ASSIGNMENT_TEXT] ?? '');
        $body1 = trim($remark1.($remark1 && $assignment ? "\n\n" : '').$assignment);
        if ($body1 !== '') {
            DB::table('grievance_action')->insert([
                'grievance_id' => $grievanceId,
                'type' => $actionType,
                'body' => $body1,
                'created_by_id' => $this->lookupUser($row[self::COL_ACTION_CREATED_BY] ?? null),
                'created_at' => $this->parseDate($row[self::COL_ACTION_CREATED] ?? null) ?? $receivedAt,
                'updated_at' => now(),
            ]);
        }

        // Mid update: REMARKS 3
        $remarks3 = trim($row[self::COL_REMARKS_3] ?? '');
        if ($remarks3 !== '') {
            DB::table('grievance_action')->insert([
                'grievance_id' => $grievanceId,
                'type' => ActionType::Update->value,
                'body' => $remarks3,
                'created_by_id' => $this->lookupUser($row[self::COL_REMARKS_CREATED_BY] ?? null),
                'created_at' => $this->parseDate($row[self::COL_REMARKS_DATE] ?? null) ?? $receivedAt,
                'updated_at' => now(),
            ]);
        }

        // Resolution
        $resolution = trim($row[self::COL_RESOLUTION_SUMMARY] ?? '');
        if ($resolution !== '') {
            DB::table('grievance_action')->insert([
                'grievance_id' => $grievanceId,
                'type' => ActionType::Resolve->value,
                'body' => $resolution,
                'created_by_id' => $this->lookupUser($row[self::COL_RESOLUTION_CREATED_BY] ?? null),
                'created_at' => $this->parseDate($row[self::COL_RESOLUTION_DATE] ?? null) ?? $receivedAt,
                'updated_at' => now(),
            ]);
        }
    }

    private function mapActionType(string $raw): string
    {
        $first = strtolower(trim(explode('|', $raw)[0]));

        return match (true) {
            str_contains($first, 'refer') => ActionType::Escalate->value,
            str_contains($first, 'investig') => ActionType::Investigate->value,
            str_contains($first, 'compens') => ActionType::Resolve->value,
            str_contains($first, 'apolog') => ActionType::Resolve->value,
            default => ActionType::Update->value,
        };
    }

    /** @param list<string> $row */
    private function insertFeedback(int $grievanceId, array $row): void
    {
        $satisfaction = trim($row[self::COL_FEEDBACK_SATISFACTION] ?? '');
        $comments = trim($row[self::COL_FEEDBACK_COMMENTS] ?? '');
        $status = trim($row[self::COL_FEEDBACK_STATUS] ?? '');

        if ($satisfaction === '' && $comments === '' && $status === '') {
            return;
        }

        $rating = match (strtolower($satisfaction)) {
            'very satisfied' => FeedbackRating::VerySatisfied->value,
            'satisfied' => FeedbackRating::Satisfied->value,
            'fairly satisfied' => FeedbackRating::Neutral->value,
            'not satisfied' => FeedbackRating::Dissatisfied->value,
            'very dissatisfied' => FeedbackRating::VeryDissatisfied->value,
            default => FeedbackRating::Neutral->value,
        };

        $submittedAt = $this->parseDate($row[self::COL_FEEDBACK_CONTACTED_ON] ?? null) ?? now();

        DB::table('grievance_feedback')->insert([
            'grievance_id' => $grievanceId,
            'rating' => $rating,
            'comment' => $comments !== '' ? $comments : null,
            'channel' => 'phone',
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt,
            'updated_at' => now(),
        ]);
    }

    private function insertStatusHistory(int $grievanceId, string $state, Carbon $receivedAt, ?int $actorId): void
    {
        DB::table('grievance_status_history')->insert([
            'grievance_id' => $grievanceId,
            'from_state' => null,
            'to_state' => $state,
            'note' => 'Imported from legacy',
            'actor_id' => $actorId,
            'occurred_at' => $receivedAt,
        ]);
    }

    private function parseDate(?string $v): ?Carbon
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        foreach (['d/m/Y', 'j/n/Y', 'd/m/y', 'j/n/y', 'Y-m-d', 'd-m-Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $v)->startOfDay();
            } catch (\Throwable) {
            }
        }
        $this->logError("unparseable date: \"{$v}\"");

        return null;
    }

    private function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full), 2);

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    private function trim(string $s, string $fallback): string
    {
        $s = trim($s);

        return $s === '' ? $fallback : $s;
    }

    private function logError(string $msg): void
    {
        $this->errors[$msg] = ($this->errors[$msg] ?? 0) + 1;
    }
}
