<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Complainer;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAction;
use App\Domain\Grievance\Models\GrievanceStatusHistory;
use App\Domain\Grievance\Models\Suspect;
use App\Domain\Identity\Models\User;
use App\Domain\Locality\Models\Locality;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reference\Models\HowReported;
use App\Domain\Reference\Models\Priority;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds reference lookups, organizations, demo users, and ~20 sample
 * grievances in mixed states, attached to localities from the canonical
 * geography (seeded separately by GeographySeeder from geography.json).
 *
 * Does NOT create its own geography — it used to, with a proper-case set
 * that collided with the canonical all-caps one and created duplicates.
 * Run GeographySeeder first.
 *
 * Idempotent-ish: uses firstOrCreate for all reference data. Grievances
 * are only created when none exist, so re-running won't duplicate them.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Locality::count() === 0) {
            $this->command?->error(
                "Canonical geography is empty. Run GeographySeeder first:\n"
                ."  php artisan db:seed --class='Database\\Seeders\\GeographySeeder'"
            );

            return;
        }

        $this->command?->info('Seeding reference lookups…');
        [$types, $howReported, $priorities] = $this->seedLookups();

        $this->command?->info('Seeding organizations…');
        $orgs = $this->seedOrganizations();

        $this->command?->info('Seeding demo users (one per workflow role)…');
        $this->seedDemoUsers($orgs);

        $this->command?->info('Seeding org grievance types…');
        $this->seedOrgGrievanceTypes($orgs);

        if (Grievance::count() > 0) {
            $this->command?->warn('Grievances already exist — skipping grievance seed to avoid duplicates.');

            return;
        }

        $this->command?->info('Seeding sample grievances…');
        $this->seedGrievances($types, $howReported, $priorities, $orgs);
    }

    /**
     * Creates demo users covering each workflow role so reviewers can log in
     * as each persona and observe org-scoped visibility. Passwords kept
     * deliberately predictable for the demo; rotate in any real deployment.
     *
     * @param  array<string, Organization>  $orgs
     */
    private function seedDemoUsers(array $orgs): void
    {
        $personas = [
            // ACC — reviewer + data operator.
            ['username' => 'acc_reviewer', 'name' => 'ACC Reviewer', 'email' => 'acc@grm-sl.local',
             'org' => 'Anti-Corruption Commission', 'role' => 'acc-reviewer'],
            ['username' => 'grm_operator', 'name' => 'GRM Data Operator', 'email' => 'operator@grm-sl.local',
             'org' => 'Anti-Corruption Commission', 'role' => 'grm-data-operator'],

            // FCC — org-admin + GRM officer + two organization-officers.
            ['username' => 'fcc_admin',    'name' => 'FCC Admin',       'email' => 'fcc-admin@grm-sl.local',
             'org' => 'Local Council - Freetown', 'role' => 'org-admin'],
            ['username' => 'fcc_grm',      'name' => 'Alusine Kanu',    'email' => 'akanu.fcc@grm-sl.local',
             'org' => 'Local Council - Freetown', 'role' => 'grm-officer'],
            ['username' => 'fcc_officer',  'name' => 'Mariama Koroma',  'email' => 'mkoroma.fcc@grm-sl.local',
             'org' => 'Local Council - Freetown', 'role' => 'organization-officer'],
            ['username' => 'fcc_fofana',   'name' => 'Sheku Fofana',    'email' => 'sfofana.fcc@grm-sl.local',
             'org' => 'Local Council - Freetown', 'role' => 'organization-officer'],

            // NaCSA — org-admin + GRM officer + officer.
            ['username' => 'nacsa_admin',  'name' => 'NaCSA Admin',     'email' => 'nacsa-admin@grm-sl.local',
             'org' => 'National Commission for Social Action', 'role' => 'org-admin'],
            ['username' => 'nacsa_grm',    'name' => 'Isata Mansaray',  'email' => 'imansaray.nacsa@grm-sl.local',
             'org' => 'National Commission for Social Action', 'role' => 'grm-officer'],
            ['username' => 'nacsa_officer','name' => 'Sorie Bah',       'email' => 'sbah.nacsa@grm-sl.local',
             'org' => 'National Commission for Social Action', 'role' => 'organization-officer'],
        ];

        foreach ($personas as $p) {
            $user = User::firstOrNew(['username' => $p['username']]);
            $user->name = $p['name'];
            $user->email = $p['email'];
            $user->phone_number = '+2327'.rand(1000000, 9999999);
            $user->password = bcrypt('ChangeMe123!');
            $user->organization_id = $orgs[$p['org']]->id;
            $user->save();
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$p['role']]);
        }
    }

    /** @param array<string, \App\Domain\Organization\Models\Organization> $orgs */
    private function seedOrgGrievanceTypes(array $orgs): void
    {
        $typesByOrg = [
            'Anti-Corruption Commission' => [
                'Corruption / Bribery',
                'GBV',
            ],
            'Local Council - Freetown' => [
                'Payments',
            ],
            'National Commission for Social Action' => [
                'Payments',
                'SIM Issues',
                'Household Selection',
                'Other',
                'MISSING DATA',
                'Administrative',
                'Identification',
            ],
        ];

        foreach ($typesByOrg as $orgName => $labels) {
            $org = $orgs[$orgName] ?? null;
            if ($org === null) {
                continue;
            }

            foreach ($labels as $label) {
                \App\Domain\Grievance\Models\OrgGrievanceType::firstOrCreate(
                    ['organization_id' => $org->id, 'label' => $label],
                );
            }
        }
    }

    /**
     * @return array{0: array<string, GrievanceType>, 1: array<string, HowReported>, 2: array<string, Priority>}
     */
    private function seedLookups(): array
    {
        // Trimmed to the three categories actually used in production:
        // Corruption (ACC cases), Administrative (everything else), Gender-Based Violence.
        $typeNames = ['Corruption', 'Administrative', 'Gender-Based Violence'];
        $types = [];
        foreach ($typeNames as $name) {
            $types[$name] = GrievanceType::firstOrCreate(['name' => $name]);
        }

        $howReportedNames = ['Phone Call', 'SMS', 'In Person', 'Letter', 'Email', 'Community Meeting'];
        $howReported = [];
        foreach ($howReportedNames as $name) {
            $howReported[$name] = HowReported::firstOrCreate(['name' => $name]);
        }

        $priorities = [
            'Low'      => ['ranking' => 10, 'response_time_hours' => 72, 'resolution_time_hours' => 720],
            'Medium'   => ['ranking' => 20, 'response_time_hours' => 24, 'resolution_time_hours' => 336],
            'High'     => ['ranking' => 30, 'response_time_hours' => 4,  'resolution_time_hours' => 168],
            'Critical' => ['ranking' => 40, 'response_time_hours' => 1,  'resolution_time_hours' => 48],
        ];
        $prioritiesOut = [];
        foreach ($priorities as $name => $attrs) {
            $prioritiesOut[$name] = Priority::firstOrCreate(['name' => $name], $attrs);
        }

        return [$types, $howReported, $prioritiesOut];
    }

    /** @return array<string, Organization> */
    private function seedOrganizations(): array
    {
        // The three organisations actually running on GRM-SL. Programmes are
        // created case-by-case inside the app, not seeded here.
        $names = [
            'Anti-Corruption Commission'            => 'ACC',
            'Local Council - Freetown'              => 'FCC',
            'National Commission for Social Action' => 'NaCSA',
        ];
        $out = [];
        foreach ($names as $name => $acronym) {
            $out[$name] = Organization::firstOrCreate(['name' => $name], ['acronym' => $acronym]);
        }

        return $out;
    }

    /**
     * @param  array<string, Region>     $regions
     * @param  array<string, District>   $districts
     * @param  array<string, Chiefdom>   $chiefdoms
     * @param  array<string, Section>    $sections
     * @param  array<string, Locality>   $localities
     * @param  array<string, GrievanceType> $types
     * @param  array<string, HowReported> $howReported
     * @param  array<string, Priority>   $priorities
     * @param  array<string, Organization> $orgs
     */
    private function seedGrievances(
        array $types, array $howReported, array $priorities, array $orgs,
    ): void {
        // Sample canonical localities (with the full section→chiefdom→district→
        // region chain preloaded). Demo grievances are bound to real places from
        // geography.json rather than this seeder fabricating its own set.
        $sampleLocalities = Locality::with('section.chiefdom.district.region')
            ->inRandomOrder()
            ->limit(40)
            ->get()
            ->values();
        if ($sampleLocalities->isEmpty()) {
            $this->command?->error('No canonical localities — run GeographySeeder first.');

            return;
        }
        // Complainant name pool — authentically Sierra Leonean.
        $complainants = [
            ['Fatmata', 'Kamara', 'F'],
            ['Mohamed', 'Sesay', 'M'],
            ['Aminata', 'Bangura', 'F'],
            ['Abu',     'Conteh',  'M'],
            ['Zainab',  'Jalloh',  'F'],
            ['Ibrahim', 'Turay',   'M'],
            ['Mariama', 'Koroma',  'F'],
            ['Alusine', 'Kanu',    'M'],
            ['Isata',   'Mansaray','F'],
            ['Sheku',   'Fofana',  'M'],
            ['Hawa',    'Sankoh',  'F'],
            ['Sorie',   'Bah',     'M'],
        ];

        // Suspect name pool (officials / other parties).
        $suspects = [
            ['Lansana', 'Kargbo',  'M', 'District Officer'],
            ['Musa',    'Jalloh',  'M', 'Contractor'],
            ['Adama',   'Williams','F', 'Headteacher'],
            ['Foday',   'Swaray',  'M', 'Chief'],
        ];

        // Grievance templates — (summary, description, type, priority, state).
        $templates = [
            [
                'Borehole pump broken in Kissy — community without water for 8 days',
                'The main borehole serving our section has been non-functional for over a week. Approximately 400 households are affected. Council has been notified twice with no response.',
                'Administrative', 'High', GrievanceState::InProgress, 'Kissy',
            ],
            [
                'Teacher salaries delayed at Bo Government Primary for the third month',
                'Teachers at Bo Government Primary School have not been paid for three consecutive months. Staff are threatening to strike. Impacts approximately 600 children.',
                'Administrative', 'High', GrievanceState::UnderReview, 'Bo Town',
            ],
            [
                'Land boundary dispute between neighbouring farmers in Panguma',
                'Two families are in dispute over a cocoa farm boundary. Mediation attempts by the section chief have failed. Risk of violence escalating.',
                'Administrative', 'Medium', GrievanceState::Submitted, 'Panguma',
            ],
            [
                'Allegation: council official demanding bribes for market permits',
                'Traders at the central market report that a specific council official is demanding payments of 200,000 Le to issue monthly permits. This happens over the past 6 weeks.',
                'Corruption', 'High', GrievanceState::InProgress, 'Freetown',
            ],
            [
                'Sewage overflow near primary school in Calaba Town',
                'Untreated sewage is flowing in a drain adjacent to the primary school playground. Children have reported skin rashes. Has been flagged twice to the sanitation office.',
                'Administrative', 'Critical', GrievanceState::InProgress, 'Calaba Town',
            ],
            [
                'Health clinic out of stock of malaria medication',
                'The government health clinic has had no first-line malaria treatment for 2 weeks. Patients are being turned away or asked to purchase privately at inflated prices.',
                'Administrative', 'High', GrievanceState::Resolved, 'Kenema Town',
            ],
            [
                'Road impassable to Mabonto village since heavy rains',
                'The feeder road linking Mabonto to the main highway is completely cut off since 21 August. Farmers cannot bring produce to market. 5 villages affected.',
                'Administrative', 'Medium', GrievanceState::Closed, 'Mabonto',
            ],
            [
                'Reports of domestic violence at household in Makeni — police inaction',
                'Neighbours have repeatedly reported incidents at a household. Police have been called 4 times in the last month with no meaningful response. Situation is deteriorating.',
                'Gender-Based Violence', 'Critical', GrievanceState::Escalated, 'Makeni Town',
            ],
            [
                'School building in Pujehun district without functioning latrines',
                'Primary school has 180 pupils but latrines are collapsed and unusable. Girls are dropping out. Construction funds appear to have been disbursed.',
                'Administrative', 'High', GrievanceState::UnderReview, 'Pujehun',
            ],
            [
                'Discrimination in job hiring at local NGO office',
                'Applicant with required qualifications alleges they were passed over for a position due to ethnicity. Multiple witnesses available.',
                'Administrative', 'Medium', GrievanceState::Rejected, 'Freetown',
            ],
            [
                'Water quality complaint — Waterloo — smell and colour',
                'Tap water in Waterloo has been discoloured and smelly since last Tuesday. Several households report stomach illness. Water company has not responded.',
                'Administrative', 'High', GrievanceState::InProgress, 'Waterloo',
            ],
            [
                'Overloading and dangerous driving by commercial vehicles on Magburaka road',
                'Drivers on the Magburaka-Makeni road regularly overload minibuses with 18+ passengers. Two accidents in the past month. Enforcement absent.',
                'Administrative', 'Medium', GrievanceState::Submitted, 'Magburaka',
            ],
            [
                'Alleged misuse of school feeding programme stocks in Kailahun',
                'Community monitors report that rice and oil intended for the school feeding programme is being sold in the market. Records need to be audited.',
                'Corruption', 'High', GrievanceState::InProgress, 'Kailahun',
            ],
            [
                'Unpermitted tree felling near community watershed in Gbendembu',
                'Loggers are operating near the stream that is the main water source for 3 villages. They claim to have permits but none have been shown.',
                'Administrative', 'High', GrievanceState::UnderReview, 'Gbendembu',
            ],
            [
                'No antenatal care services at Tombo health post for 3 weeks',
                'The midwife has been reassigned and no replacement sent. Pregnant women travel 30km for antenatal visits. 40+ women currently affected.',
                'Administrative', 'Critical', GrievanceState::InProgress, 'Tombo',
            ],
            [
                'Employment contract not honoured after 6 months',
                'Worker was hired under a written contract by a private company but has received only partial payments. Company claims cash flow issues.',
                'Administrative', 'Low', GrievanceState::Closed, 'Port Loko Town',
            ],
            [
                'Market stall demolition in Kambia without notice',
                'Council demolished 20+ market stalls last week without prior notice to vendors. Loss of livelihoods. Vendors claim compensation was promised but never paid.',
                'Administrative', 'High', GrievanceState::Resolved, 'Kambia Town',
            ],
            [
                'Abandoned construction project in Lumley — safety hazard',
                'Half-built hotel abandoned 2 years ago is now used by children as a play area. Steel rebar exposed, open pits. Accident waiting to happen.',
                'Administrative', 'Medium', GrievanceState::Trashed, 'Lumley',
            ],
            [
                'Dispute over paramount chief election in Luawa',
                'Two factions claim victory in the recent paramount chief election. Tensions high. Traditional leaders council has not convened.',
                'Administrative', 'High', GrievanceState::InProgress, 'Kailahun',
            ],
            [
                'School fees still being charged at public school in Goderich',
                'Government policy states no fees at public primary. Headteacher charges 50,000 Le per term claiming "development fee". Families unable to pay keeping children at home.',
                'Administrative', 'Medium', GrievanceState::UnderReview, 'Goderich',
            ],
        ];

        $admin = User::where('username', 'admin')->first();

        foreach ($templates as $i => [$summary, $description, $typeName, $priorityName, $state, $localityHint]) {
            // $localityHint is cosmetic — kept in the template for narrative
            // context. Actual FK is a real canonical locality, rotated through
            // the sample pool so grievances spread across all five regions.
            $locality = $sampleLocalities[$i % $sampleLocalities->count()];
            $section = $locality->section;
            $chiefdom = $section?->chiefdom;
            $district = $chiefdom?->district;
            $region = $district?->region;

            $receivedAt = now()->subDays(rand(0, 60))->subHours(rand(0, 23));
            $resolvedAt = in_array($state, [GrievanceState::Resolved, GrievanceState::Closed], true)
                ? $receivedAt->copy()->addDays(rand(2, 14))
                : null;
            $closedAt = $state === GrievanceState::Closed
                ? $resolvedAt?->copy()->addDays(rand(1, 5))
                : null;

            $isAnonymous = $i % 5 === 0;

            // Type → org routing. Corruption and GBV stick with ACC per the
            // workflow rule; Administrative rotates between FCC and NaCSA so
            // the demo exercises more than one owning org.
            // Cases still in intake (Submitted/UnderReview) have no
            // classification yet — ACC will assign it.
            $adminOrg = ($i % 2 === 0) ? 'Local Council - Freetown' : 'National Commission for Social Action';
            $typeToOrg = [
                'Corruption' => 'Anti-Corruption Commission',
                'Gender-Based Violence' => 'Anti-Corruption Commission',
                'Administrative' => $adminOrg,
            ];
            $classifiedOrgId = null;
            if (! in_array($state, [GrievanceState::Submitted, GrievanceState::UnderReview], true)) {
                $targetOrg = $typeToOrg[$typeName] ?? null;
                $classifiedOrgId = $targetOrg !== null ? ($orgs[$targetOrg]->id ?? null) : null;
            }

            $grievance = Grievance::create([
                'g_number' => sprintf('GRM-%d-%06d', (int) $receivedAt->year, $i + 1),
                'summary' => $summary,
                'description' => $description,
                'grievance_type_id' => $types[$typeName]->id,
                'how_reported_id' => array_values($howReported)[$i % count($howReported)]->id,
                'priority_id' => $priorities[$priorityName]->id,
                'state' => $state->value,
                'is_anonymous' => $isAnonymous,
                'classified_organization_id' => $classifiedOrgId,
                'region_id' => $region?->id,
                'district_id' => $district?->id,
                'chiefdom_id' => $chiefdom?->id,
                'section_id' => $section?->id,
                'locality_id' => $locality?->id,
                'received_at' => $receivedAt,
                'resolved_at' => $resolvedAt,
                'closed_at' => $closedAt,
                'review_comment' => $state === GrievanceState::Rejected
                    ? 'Out of scope for GRM — routed to appropriate channel.'
                    : null,
                'reviewed_at' => $state !== GrievanceState::Submitted ? $receivedAt->copy()->addHours(rand(2, 12)) : null,
                'reviewed_by_id' => $state !== GrievanceState::Submitted ? $admin?->id : null,
            ]);

            // Assign in-progress/resolved cases to specific officers so we
            // can demo the "only the assigned officer edits" rule. Picks
            // a regular officer in the case's owning org.
            if (
                $classifiedOrgId !== null
                && in_array($state, [GrievanceState::InProgress, GrievanceState::Resolved, GrievanceState::Closed, GrievanceState::Escalated], true)
            ) {
                $officer = User::where('organization_id', $classifiedOrgId)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'organization-officer'))
                    ->inRandomOrder()
                    ->first();
                if ($officer !== null) {
                    $grievance->update(['assigned_officer_id' => $officer->id]);
                }
            }

            // Complainer (unless anonymous).
            if (! $isAnonymous) {
                [$first, $last, $gender] = $complainants[$i % count($complainants)];
                Complainer::create([
                    'grievance_id' => $grievance->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'email' => strtolower("{$first}.{$last}@example.sl"),
                    'phone_number' => '+2327'.rand(1000000, 9999999),
                    'address' => $localityName.', '.($chiefdom?->name ?? '').' Chiefdom',
                    'region_id' => $region?->id,
                    'district_id' => $district?->id,
                    'chiefdom_id' => $chiefdom?->id,
                    'section_id' => $section?->id,
                    'locality_id' => $locality?->id,
                ]);
            }

            // Occasional suspect for cases involving people.
            if (in_array($typeName, ['Corruption', 'Gender-Based Violence', 'Administrative', 'Administrative'], true)) {
                [$sFirst, $sLast, $sGender, $sTitle] = $suspects[$i % count($suspects)];
                Suspect::create([
                    'grievance_id' => $grievance->id,
                    'first_name' => $sFirst,
                    'last_name' => $sLast,
                    'gender' => $sGender,
                    'title' => $sTitle,
                    'phone_number' => '+2323'.rand(1000000, 9999999),
                    'region_id' => $region?->id,
                    'district_id' => $district?->id,
                ]);
            }

            // Initial status history entry (submission).
            GrievanceStatusHistory::create([
                'grievance_id' => $grievance->id,
                'from_state' => null,
                'to_state' => GrievanceState::Submitted->value,
                'note' => 'Submitted',
                'actor_id' => null,
                'occurred_at' => $receivedAt,
            ]);

            // Add subsequent state history entries for cases past Submitted.
            $progression = [
                GrievanceState::Submitted,
                GrievanceState::UnderReview,
                GrievanceState::InProgress,
                GrievanceState::Resolved,
                GrievanceState::Closed,
            ];
            $idx = array_search($state, $progression, true);
            if ($idx !== false && $idx > 0) {
                for ($j = 1; $j <= $idx; $j++) {
                    GrievanceStatusHistory::create([
                        'grievance_id' => $grievance->id,
                        'from_state' => $progression[$j - 1]->value,
                        'to_state' => $progression[$j]->value,
                        'note' => null,
                        'actor_id' => $admin?->id,
                        'occurred_at' => $receivedAt->copy()->addHours(rand(2 * $j, 12 * $j)),
                    ]);
                }
            }

            // Handle terminal non-progression states.
            if (in_array($state, [GrievanceState::Rejected, GrievanceState::Trashed, GrievanceState::Escalated], true)) {
                GrievanceStatusHistory::create([
                    'grievance_id' => $grievance->id,
                    'from_state' => GrievanceState::UnderReview->value,
                    'to_state' => $state->value,
                    'note' => $grievance->review_comment,
                    'actor_id' => $admin?->id,
                    'occurred_at' => $receivedAt->copy()->addHours(rand(4, 24)),
                ]);
            }

            // Add an "Investigate" action for cases in-progress or later.
            if (in_array($state, [GrievanceState::InProgress, GrievanceState::Resolved, GrievanceState::Closed, GrievanceState::Escalated], true)) {
                GrievanceAction::create([
                    'grievance_id' => $grievance->id,
                    'type' => ActionType::Investigate->value,
                    'body' => "Initial investigation started. Contacted {$chiefdom?->name} chiefdom office and scheduled site visit.",
                    'created_by_id' => $admin?->id,
                    'updated_by_id' => $admin?->id,
                    'created_at' => $receivedAt->copy()->addHours(rand(6, 24)),
                    'updated_at' => $receivedAt->copy()->addHours(rand(6, 24)),
                ]);
            }

            // Resolution action for resolved/closed.
            if (in_array($state, [GrievanceState::Resolved, GrievanceState::Closed], true)) {
                GrievanceAction::create([
                    'grievance_id' => $grievance->id,
                    'type' => ActionType::Resolve->value,
                    'body' => 'Issue resolved. Service restored / corrective action taken in coordination with relevant authorities.',
                    'created_by_id' => $admin?->id,
                    'updated_by_id' => $admin?->id,
                    'created_at' => $resolvedAt,
                    'updated_at' => $resolvedAt,
                ]);
            }
        }

        $this->command?->info('Seeded '.count($templates).' grievances.');
    }
}
