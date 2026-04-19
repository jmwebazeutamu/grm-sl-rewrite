<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\FeedbackRating;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Beneficiary;
use App\Domain\Grievance\Models\Classification;
use App\Domain\Grievance\Models\Complainer;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAction;
use App\Domain\Grievance\Models\GrievanceFeedback;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyGrievancesTsv extends Command
{
    protected $signature = 'grm:import-legacy-tsv
        {file : absolute path to csv/tsv export}
        {--dry-run : classify rows and print report, write nothing}
        {--limit=0 : process at most N data rows (0 = all)}
        {--delimiter=, : field delimiter (, for csv, \t for tsv)}
        {--seed-missing : auto-create reference rows (grievance_type, case_concept, area, programme, how_reported, organization) for unknown names}
        {--update-locations-only : do not create grievances; for existing ones (matched by g_number) only re-resolve and update district/chiefdom/section/locality FKs with alias map applied}';

    protected $description = 'Import grievances from a delimited legacy export (headers in row 1).';

    /* Column positions (0-indexed) — matches CSV with no received_on column (53 cols) */
    private const COL_ID = 0;
    private const COL_REF = 1;
    private const COL_STATUS = 2;
    private const COL_SUMMARY = 3;
    private const COL_DESCRIPTION = 4;
    private const COL_DATE_CREATED = 5;
    private const COL_DATE_RESOLVED = 6;
    private const COL_LAST_UPDATED = 7;
    private const COL_DATE_REVIEWED = 8;
    private const COL_HOW_REPORTED = 9;
    private const COL_REGION = 10;
    private const COL_DISTRICT = 11;
    private const COL_CHIEFDOM = 12;
    private const COL_SECTION = 13;
    private const COL_LOCALITY = 14;
    private const COL_REVIEW_OUTCOME = 15;
    private const COL_BENEFICIARIES = 16;
    private const COL_COMPLAINERS = 17;
    private const COL_ORG = 18;
    private const COL_GRIEVANCE_TYPE = 19;
    private const COL_CASE_CONCEPT = 20;
    private const COL_PROGRAMME = 21;
    private const COL_RESPONSIBLE_AREA = 22;
    private const COL_ACTION_ANALYSIS = 23;
    private const COL_ACTION_TYPE_NAME = 24;
    private const COL_ASSIGNED_STAFF = 25;
    private const COL_ACTION_TARGET_DATE = 26;
    private const COL_ACTION_CREATED = 27;
    private const COL_REMARKS_BODY = 31;
    private const COL_REMARKS_CREATED = 34;
    private const COL_RESOLUTION_SUMMARY = 38;
    private const COL_RESOLUTION_CREATED = 41;
    private const COL_FEEDBACK_COMMENTS = 43;
    private const COL_FEEDBACK_SATISFACTION = 45;
    private const COL_FEEDBACK_CONTACTED_ON = 46;

    /** @var array<string, array<string, int>> ref table => lowercase name => id */
    private array $refs = [];

    /** @var array<string, array<string, int>> unknown refs bucket => name => count */
    private array $unknowns = [];

    /** @var array<int, array{id: string, field: string, value: string}> */
    private array $dateErrors = [];

    /** @var array<string, int> legacy status => count */
    private array $statusCounts = [];

    /** @var array<string, int> rewrite state => count */
    private array $stateCounts = [];

    /** @var array<string, int> bucket => count seeded */
    private array $seededCounts = [];

    private int $parsedRows = 0;
    private int $skippedJunk = 0;
    private int $skippedDupe = 0;
    private int $willImport = 0;

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $this->loadReferences();
        $this->info("Reference tables loaded.");

        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->error("Cannot open file.");

            return self::FAILURE;
        }

        $delimiterHeader = $this->option('delimiter') ?: ',';
        if ($delimiterHeader === '\\t') {
            $delimiterHeader = "\t";
        }
        // Read header
        $header = fgetcsv($handle, 0, $delimiterHeader);
        if ($header === false) {
            $this->error("Empty file.");

            return self::FAILURE;
        }

        $this->info('Header columns: '.count($header));

        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $delimiter = $this->option('delimiter') ?: ',';
        if ($delimiter === '\\t') {
            $delimiter = "\t";
        }

        $existingGNumbers = Grievance::pluck('id', 'g_number')->all();

        $rows = [];
        $rowNum = 1; // header is row 1
        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
            if ($this->isBlank($cols)) {
                continue;
            }
            $this->parsedRows++;
            $rows[] = ['num' => $rowNum, 'cols' => $cols];
        }
        fclose($handle);

        $this->info("Parsed {$this->parsedRows} data rows.");

        foreach ($rows as $row) {
            $this->classifyRow($row['cols'], $existingGNumbers, $dry);
        }

        $this->printReport();

        if (! $dry) {
            $this->warn('Live import complete. Verify in UI and logs.');
        } else {
            $this->line('');
            $this->warn('Dry-run only. No rows written.');
        }

        return self::SUCCESS;
    }

    private function loadReferences(): void
    {
        $tables = [
            'organization' => 'organization',
            'grievance_type' => 'grievance_type',
            'case_concept' => 'case_concept',
            'programme' => 'programme',
            'area' => 'area',
            'region' => 'region',
            'district' => 'district',
            'chiefdom' => 'chiefdom',
            'section' => 'section',
            'locality' => 'locality',
            'how_reported' => 'how_reported',
            'review_outcome' => 'review_outcome',
        ];
        foreach ($tables as $key => $table) {
            try {
                $this->refs[$key] = DB::table($table)
                    ->select('id', 'name')
                    ->get()
                    ->mapWithKeys(fn ($r) => [mb_strtolower(trim((string) $r->name)) => (int) $r->id])
                    ->all();
            } catch (\Throwable $e) {
                $this->refs[$key] = [];
            }
        }

        // Also index organizations by acronym so CSV values like "NaCSA" match seeded rows.
        try {
            $acronymed = DB::table('organization')
                ->whereNotNull('acronym')
                ->where('acronym', '!=', '')
                ->select('id', 'acronym')
                ->get();
            foreach ($acronymed as $o) {
                $key = mb_strtolower(trim((string) $o->acronym));
                if ($key !== '' && ! isset($this->refs['organization'][$key])) {
                    $this->refs['organization'][$key] = (int) $o->id;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function isBlank(array $cols): bool
    {
        $nonBlank = array_filter($cols, fn ($v) => trim((string) $v) !== '');

        return count($nonBlank) < 3;
    }

    private function classifyRow(array $cols, array $existing, bool $dry): void
    {
        $id = trim((string) ($cols[self::COL_ID] ?? ''));
        $ref = trim((string) ($cols[self::COL_REF] ?? ''));
        $legacyStatus = strtoupper(trim((string) ($cols[self::COL_STATUS] ?? '')));
        $summary = trim((string) ($cols[self::COL_SUMMARY] ?? ''));
        $description = trim((string) ($cols[self::COL_DESCRIPTION] ?? ''));

        $this->statusCounts[$legacyStatus ?: '(blank)'] = ($this->statusCounts[$legacyStatus ?: '(blank)'] ?? 0) + 1;

        if ($this->looksJunk($summary, $description, $legacyStatus)) {
            $this->skippedJunk++;

            return;
        }

        if ($ref === '' || $summary === '') {
            $this->skippedJunk++;

            return;
        }

        $gNumber = $ref;

        if ($this->option('update-locations-only')) {
            if (! isset($existing[$gNumber])) {
                // grievance doesn't exist → nothing to update
                return;
            }
            $regionId = $this->resolveRef($cols[self::COL_REGION] ?? null, 'region');
            $districtId = $this->resolveRef($cols[self::COL_DISTRICT] ?? null, 'district');
            $chiefdomId = $this->resolveRef($cols[self::COL_CHIEFDOM] ?? null, 'chiefdom');
            $sectionId = $this->resolveRef($cols[self::COL_SECTION] ?? null, 'section');
            $localityId = $this->resolveRef($cols[self::COL_LOCALITY] ?? null, 'locality');

            if (! $dry) {
                DB::table('grievance')
                    ->where('g_number', $gNumber)
                    ->update([
                        'region_id' => $regionId,
                        'district_id' => $districtId,
                        'chiefdom_id' => $chiefdomId,
                        'section_id' => $sectionId,
                        'locality_id' => $localityId,
                        'updated_at' => now(),
                    ]);
            }
            $this->willImport++;

            return;
        }

        if (isset($existing[$gNumber])) {
            $this->skippedDupe++;

            return;
        }

        $dateCreated = $this->parseDate($cols[self::COL_DATE_CREATED] ?? null, $id, 'date_created');
        $dateResolved = $this->parseDate($cols[self::COL_DATE_RESOLVED] ?? null, $id, 'date_resolved');
        $dateReviewed = $this->parseDate($cols[self::COL_DATE_REVIEWED] ?? null, $id, 'date_reviewed');
        $receivedOn = $dateCreated; // CSV has no separate received_on column

        $hasClassification = trim((string) ($cols[self::COL_ORG] ?? '')) !== ''
            || trim((string) ($cols[self::COL_GRIEVANCE_TYPE] ?? '')) !== '';
        $state = $this->mapStatus($legacyStatus, $hasClassification);
        $this->stateCounts[$state->value] = ($this->stateCounts[$state->value] ?? 0) + 1;

        $this->willImport++;

        // Resolve refs for both dry-run (to populate unknowns report) and live import.
        // Order matters for --seed-missing: parents before children (org before programme, gt before case_concept).
        $orgId = $this->resolveRef($cols[self::COL_ORG] ?? null, 'organization');
        $gtId = $this->resolveRef($cols[self::COL_GRIEVANCE_TYPE] ?? null, 'grievance_type');
        if ($gtId === null) {
            // grievance.grievance_type_id is NOT NULL; fall back to the "Unclassified" sentinel.
            $gtId = $this->refs['grievance_type']['unclassified'] ?? null;
        }
        $ccId = $this->resolveRef($cols[self::COL_CASE_CONCEPT] ?? null, 'case_concept', ['grievance_type_id' => $gtId]);
        $progId = $this->resolveRef($cols[self::COL_PROGRAMME] ?? null, 'programme', ['organization_id' => $orgId]);
        $areaId = $this->resolveRef($cols[self::COL_RESPONSIBLE_AREA] ?? null, 'area');
        $regionId = $this->resolveRef($cols[self::COL_REGION] ?? null, 'region');
        $districtId = $this->resolveRef($cols[self::COL_DISTRICT] ?? null, 'district');
        $chiefdomId = $this->resolveRef($cols[self::COL_CHIEFDOM] ?? null, 'chiefdom');
        $sectionId = $this->resolveRef($cols[self::COL_SECTION] ?? null, 'section');
        $localityId = $this->resolveRef($cols[self::COL_LOCALITY] ?? null, 'locality');
        $howReportedId = $this->resolveRef($cols[self::COL_HOW_REPORTED] ?? null, 'how_reported');

        if ($dry) {
            return;
        }

        DB::transaction(function () use ($cols, $gNumber, $state, $dateResolved, $dateReviewed, $dateCreated, $summary, $description, $orgId, $gtId, $ccId, $progId, $areaId, $regionId, $districtId, $chiefdomId, $sectionId, $localityId, $howReportedId) {
            $receivedOn = $dateCreated;

            $g = Grievance::create([
                'g_number' => $gNumber,
                'summary' => $summary !== '' ? mb_substr($summary, 0, 500) : '(no summary)',
                'description' => $description,
                'state' => $state,
                'is_anonymous' => false,
                'grievance_type_id' => $gtId,
                'how_reported_id' => $howReportedId,
                'region_id' => $regionId,
                'district_id' => $districtId,
                'chiefdom_id' => $chiefdomId,
                'section_id' => $sectionId,
                'locality_id' => $localityId,
                'received_at' => $receivedOn ?? $dateCreated ?? now(),
                'reviewed_at' => $dateReviewed,
                'resolved_at' => $dateResolved,
                'closed_at' => $state === GrievanceState::Closed ? ($dateResolved ?? $dateReviewed) : null,
                'classified_organization_id' => $orgId,
                'classified_programme_id' => $progId,
                'classified_case_concept_id' => $ccId,
                'classified_area_id' => $areaId,
            ]);

            foreach ($this->splitNames($cols[self::COL_COMPLAINERS] ?? '') as $name) {
                [$first, $last] = $this->splitFirstLast($name);
                Complainer::create([
                    'grievance_id' => $g->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'organization_id' => $orgId,
                    'region_id' => $regionId,
                    'district_id' => $districtId,
                    'chiefdom_id' => $chiefdomId,
                    'section_id' => $sectionId,
                    'locality_id' => $localityId,
                ]);
            }

            foreach ($this->splitNames($cols[self::COL_BENEFICIARIES] ?? '') as $name) {
                Beneficiary::create([
                    'grievance_id' => $g->id,
                    'name' => $name,
                    'implementing_agency_id' => $orgId,
                    'social_programme_id' => $progId,
                ]);
            }

            if ($orgId || $progId || $ccId || $areaId) {
                Classification::create([
                    'grievance_id' => $g->id,
                    'organization_id' => $orgId,
                    'programme_id' => $progId,
                    'case_concept_id' => $ccId,
                    'responsible_area_id' => $areaId,
                ]);
            }

            $this->createTimeline($g, $cols);
            $this->createFeedback($g, $cols);
        });
    }

    private function createTimeline(Grievance $g, array $cols): void
    {
        $actionBody = trim(strip_tags((string) ($cols[self::COL_ACTION_ANALYSIS] ?? '')));
        if ($actionBody !== '') {
            GrievanceAction::forceCreate([
                'grievance_id' => $g->id,
                'type' => ActionType::Update,
                'body' => mb_substr($actionBody, 0, 5000),
                'created_at' => $this->parseDate($cols[self::COL_ACTION_CREATED] ?? null, '', 'action.created') ?? now(),
                'updated_at' => now(),
            ]);
        }

        $remarksBody = trim(strip_tags((string) ($cols[self::COL_REMARKS_BODY] ?? '')));
        if ($remarksBody !== '') {
            GrievanceAction::forceCreate([
                'grievance_id' => $g->id,
                'type' => ActionType::Update,
                'body' => mb_substr($remarksBody, 0, 5000),
                'created_at' => $this->parseDate($cols[self::COL_REMARKS_CREATED] ?? null, '', 'remarks.created') ?? now(),
                'updated_at' => now(),
            ]);
        }

        $resolutionBody = trim(strip_tags((string) ($cols[self::COL_RESOLUTION_SUMMARY] ?? '')));
        if ($resolutionBody !== '') {
            GrievanceAction::forceCreate([
                'grievance_id' => $g->id,
                'type' => ActionType::Resolve,
                'body' => mb_substr($resolutionBody, 0, 5000),
                'created_at' => $this->parseDate($cols[self::COL_RESOLUTION_CREATED] ?? null, '', 'resolution.created') ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createFeedback(Grievance $g, array $cols): void
    {
        $comments = trim(strip_tags((string) ($cols[self::COL_FEEDBACK_COMMENTS] ?? '')));
        $satisfaction = trim((string) ($cols[self::COL_FEEDBACK_SATISFACTION] ?? ''));
        if ($comments === '' && $satisfaction === '') {
            return;
        }

        GrievanceFeedback::create([
            'grievance_id' => $g->id,
            'rating' => $this->mapSatisfaction($satisfaction),
            'comment' => mb_substr($comments, 0, 2000),
            'channel' => 'web',
            'submitted_at' => $this->parseDate($cols[self::COL_FEEDBACK_CONTACTED_ON] ?? null, '', 'feedback.contacted_on') ?? now(),
        ]);
    }

    private function mapSatisfaction(string $label): FeedbackRating
    {
        return match (strtolower(trim($label))) {
            'satisfied' => FeedbackRating::Satisfied,
            'fairly satisfied' => FeedbackRating::Neutral,
            'not satisfied' => FeedbackRating::Dissatisfied,
            default => FeedbackRating::Neutral,
        };
    }

    /** @var array<string, array<string, string>> alias map: bucket => lowercase-raw => canonical */
    private array $aliasMap = [
        'district' => [
            'kambia 1' => 'KAMBIA',
            'portloko' => 'PORT LOKO',
        ],
        'chiefdom' => [
            'fiama 0' => 'Fiama',
            'tankoro 0' => 'Tankoro',
            'nimikoro 0' => 'Nimikoro',
        ],
    ];

    private function resolveRef(?string $raw, string $bucket, array $parentCols = []): ?int
    {
        $name = trim((string) $raw);
        if ($name === '') {
            return null;
        }
        $key = mb_strtolower($name);
        if (isset($this->aliasMap[$bucket][$key])) {
            $key = mb_strtolower($this->aliasMap[$bucket][$key]);
        }
        if (isset($this->refs[$bucket][$key])) {
            return $this->refs[$bucket][$key];
        }

        if ($this->option('seed-missing')) {
            // case_concept needs a non-null grievance_type_id; programme needs a non-null organization_id.
            if ($bucket === 'case_concept' && empty($parentCols['grievance_type_id'])) {
                $this->unknowns[$bucket][$name.' (missing grievance_type parent)'] = ($this->unknowns[$bucket][$name.' (missing grievance_type parent)'] ?? 0) + 1;

                return null;
            }
            if ($bucket === 'programme' && empty($parentCols['organization_id'])) {
                $this->unknowns[$bucket][$name.' (missing organization parent)'] = ($this->unknowns[$bucket][$name.' (missing organization parent)'] ?? 0) + 1;

                return null;
            }

            $id = $this->insertRef($bucket, $name, $parentCols);
            if ($id !== null) {
                $this->refs[$bucket][$key] = $id;
                $this->seededCounts[$bucket] = ($this->seededCounts[$bucket] ?? 0) + 1;

                return $id;
            }
        }

        $this->unknowns[$bucket][$name] = ($this->unknowns[$bucket][$name] ?? 0) + 1;

        return null;
    }

    private function insertRef(string $bucket, string $name, array $extra): ?int
    {
        $tables = [
            'organization' => 'organization',
            'grievance_type' => 'grievance_type',
            'case_concept' => 'case_concept',
            'programme' => 'programme',
            'area' => 'area',
            'how_reported' => 'how_reported',
            'review_outcome' => 'review_outcome',
        ];
        if (! isset($tables[$bucket])) {
            return null; // don't auto-seed locality/region/district/chiefdom/section
        }
        $table = $tables[$bucket];

        $row = ['name' => $name] + $extra + [
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($bucket === 'organization') {
            $row['sla_days'] = $row['sla_days'] ?? 30;
        }
        if ($bucket === 'programme') {
            $row['status'] = $row['status'] ?? 'active';
            $row['active'] = $row['active'] ?? 1;
        }

        try {
            return (int) DB::table($table)->insertGetId($row);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseDate(?string $raw, string $id, string $field): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        // Reject Excel serials
        if (ctype_digit($raw) && (int) $raw > 40000 && (int) $raw < 60000) {
            $this->dateErrors[] = ['id' => $id, 'field' => $field, 'value' => $raw];

            return null;
        }
        // Strict DD/MM/YYYY
        if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $raw, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($d >= 1 && $d <= 31 && $mo >= 1 && $mo <= 12) {
                try {
                    return Carbon::create($y, $mo, $d, 0, 0, 0);
                } catch (\Throwable) {
                    $this->dateErrors[] = ['id' => $id, 'field' => $field, 'value' => $raw];

                    return null;
                }
            }
        }
        $this->dateErrors[] = ['id' => $id, 'field' => $field, 'value' => $raw];

        return null;
    }

    private function mapStatus(string $status, bool $hasClassification): GrievanceState
    {
        return match (strtoupper($status)) {
            'SUBMITTED', 'NEW' => GrievanceState::Submitted,
            'IN PROGRESS', 'PENDING', 'NO RESOLUTION' => GrievanceState::InProgress,
            'RESOLVED' => GrievanceState::Resolved,
            'CLOSED' => GrievanceState::Closed,
            'ESCALATED' => GrievanceState::Escalated,
            'REJECTED' => GrievanceState::Rejected,
            'TRASHED' => GrievanceState::Trashed,
            'UN-CLASSIFIED' => GrievanceState::UnderReview,
            'UN-ASSIGNED' => GrievanceState::Categorized,
            'NO REMARKS' => GrievanceState::Accepted,
            default => $hasClassification ? GrievanceState::UnderReview : GrievanceState::Submitted,
        };
    }

    private function looksJunk(string $summary, string $description, string $status): bool
    {
        $joined = mb_strtolower($summary.' '.$description);
        $junkSubstrings = ['test case', 'testing', 'bms test', 'api test', 'tfyguhijok', 'fgvhbjnk', 'shfbsksgk', 'eufijf', 'hbjnkml', 'unfinished', 'frttyguhijo'];
        foreach ($junkSubstrings as $j) {
            if ($j !== '' && str_contains($joined, $j)) {
                return true;
            }
        }
        if (mb_strlen(trim($summary)) <= 2 && mb_strlen(trim($description)) <= 10 && ! in_array($status, ['NEW', 'PENDING', 'CLOSED'], true)) {
            return true;
        }

        return false;
    }

    /** @return list<string> */
    private function splitNames(string $raw): array
    {
        $names = array_map('trim', preg_split('~[\|]~', trim($raw)) ?: []);
        $names = array_filter($names, fn ($n) => $n !== '');

        return array_values(array_unique($names));
    }

    /** @return array{0: string, 1: string} */
    private function splitFirstLast(string $name): array
    {
        $parts = preg_split('~\s+~', trim($name)) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }
        $first = array_shift($parts);

        return [$first, implode(' ', $parts)];
    }

    private function printReport(): void
    {
        $this->line('');
        $this->info('═══ IMPORT REPORT ═══');
        $this->line("Parsed rows:      {$this->parsedRows}");
        $this->line("Will import:      {$this->willImport}");
        $this->line("Skipped (junk):   {$this->skippedJunk}");
        $this->line("Skipped (dup):    {$this->skippedDupe}");

        $this->line('');
        $this->info('Legacy status distribution:');
        arsort($this->statusCounts);
        foreach ($this->statusCounts as $s => $c) {
            $this->line(sprintf('  %-20s %d', $s, $c));
        }

        $this->line('');
        $this->info('Rewrite state distribution (for will-import rows):');
        arsort($this->stateCounts);
        foreach ($this->stateCounts as $s => $c) {
            $this->line(sprintf('  %-20s %d', $s, $c));
        }

        if ($this->seededCounts !== []) {
            $this->line('');
            $this->info('Reference rows created this run (--seed-missing):');
            foreach ($this->seededCounts as $bucket => $count) {
                $this->line(sprintf('  %-20s %d', $bucket, $count));
            }
        }

        $this->line('');
        $this->info('Unknown reference values (will be NULL on the grievance):');
        foreach ($this->unknowns as $bucket => $map) {
            arsort($map);
            $total = array_sum($map);
            $this->line("  [{$bucket}] ({$total} occurrences, ".count($map).' distinct)');
            foreach (array_slice($map, 0, 10, true) as $name => $count) {
                $this->line(sprintf('    · %-40s %d', mb_strimwidth($name, 0, 40), $count));
            }
            if (count($map) > 10) {
                $this->line('    · ... +'.(count($map) - 10).' more');
            }
        }

        if ($this->dateErrors !== []) {
            $this->line('');
            $this->warn('Date parse errors: '.count($this->dateErrors).' (first 20 shown)');
            foreach (array_slice($this->dateErrors, 0, 20) as $e) {
                $this->line(sprintf('  id=%s field=%s value=%s', $e['id'], $e['field'], $e['value']));
            }
        }
    }
}
