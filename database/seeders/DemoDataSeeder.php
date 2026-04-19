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
use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Region;
use App\Domain\Locality\Models\Section;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reference\Models\HowReported;
use App\Domain\Reference\Models\Priority;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a browsable demo: Sierra Leone geography (Region → District →
 * Chiefdom → Section → Locality), reference lookups, organizations, and
 * ~20 sample grievances in mixed states.
 *
 * Idempotent-ish: uses firstOrCreate for all reference data. Grievances
 * are only created when none exist, so re-running won't duplicate them.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding Sierra Leone geography…');
        [$regions, $districts, $chiefdoms, $sections, $localities] = $this->seedGeography();

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
        $this->seedGrievances($regions, $districts, $chiefdoms, $sections, $localities, $types, $howReported, $priorities, $orgs);
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
            // ACC — one reviewer + one data operator.
            ['username' => 'acc_reviewer', 'name' => 'ACC Reviewer', 'email' => 'acc@grm-sl.local',
             'org' => 'Anti-Corruption Commission', 'role' => 'acc-reviewer'],
            ['username' => 'grm_operator', 'name' => 'GRM Data Operator', 'email' => 'operator@grm-sl.local',
             'org' => 'Anti-Corruption Commission', 'role' => 'grm-data-operator'],

            // MoHS — one org-admin + one GRM Officer + two regular officers.
            ['username' => 'mohs_admin',   'name' => 'MoHS Admin',          'email' => 'mohs-admin@grm-sl.local',
             'org' => 'Ministry of Health', 'role' => 'org-admin'],
            ['username' => 'mohs_grm',     'name' => 'Dr. Aminata Kamara', 'email' => 'akamara.mohs@grm-sl.local',
             'org' => 'Ministry of Health', 'role' => 'grm-officer'],
            ['username' => 'mohs_officer', 'name' => 'Mohamed Sesay',     'email' => 'msesay.mohs@grm-sl.local',
             'org' => 'Ministry of Health', 'role' => 'organization-officer'],
            ['username' => 'mohs_jalloh',  'name' => 'Zainab Jalloh',     'email' => 'zjalloh.mohs@grm-sl.local',
             'org' => 'Ministry of Health', 'role' => 'organization-officer'],

            // MBSSE — one GRM Officer + one officer.
            ['username' => 'mbsse_grm',     'name' => 'Fatmata Bangura', 'email' => 'fbangura.mbsse@grm-sl.local',
             'org' => 'Ministry of Education', 'role' => 'grm-officer'],
            ['username' => 'mbsse_officer', 'name' => 'Ibrahim Turay',   'email' => 'ituray.mbsse@grm-sl.local',
             'org' => 'Ministry of Education', 'role' => 'organization-officer'],

            // FCC — one GRM Officer + two officers.
            ['username' => 'fcc_grm',      'name' => 'Alusine Kanu',     'email' => 'akanu.fcc@grm-sl.local',
             'org' => 'Local Council - Freetown', 'role' => 'grm-officer'],
            ['username' => 'fcc_officer',  'name' => 'Mariama Koroma',   'email' => 'mkoroma.fcc@grm-sl.local',
             'org' => 'Local Council - Freetown', 'role' => 'organization-officer'],
            ['username' => 'fcc_fofana',   'name' => 'Sheku Fofana',     'email' => 'sfofana.fcc@grm-sl.local',
             'org' => 'Local Council - Freetown', 'role' => 'organization-officer'],

            // Ministry of Water Resources — one GRM Officer.
            ['username' => 'mwr_grm',      'name' => 'Isata Mansaray',   'email' => 'imansaray.mwr@grm-sl.local',
             'org' => 'Ministry of Water Resources', 'role' => 'grm-officer'],
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
            'Ministry of Health' => ['Payments', 'Inclusion/Exclusion Errors', 'SIM Card Issues', 'Service Quality'],
            'Anti-Corruption Commission' => ['Bribery', 'Misappropriation', 'Conflict of Interest'],
            'Local Council - Freetown' => ['Infrastructure Complaint', 'Market Access', 'Land Dispute Resolution'],
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
     * @return array{
     *   0: array<string, Region>,
     *   1: array<string, District>,
     *   2: array<string, Chiefdom>,
     *   3: array<string, Section>,
     *   4: array<string, Locality>,
     * }
     */
    private function seedGeography(): array
    {
        // Five regions + ~10 districts + a smaller representative set of
        // chiefdoms/sections/localities. Real SL has ~190 chiefdoms; this is
        // enough breadth to demo filtering without overwhelming the UI.
        $structure = [
            'Western Area' => [
                'Western Area Urban' => [
                    'Central'    => ['Central I'    => ['Tower Hill', 'Cotton Tree Area', 'Susan\'s Bay']],
                    'East'       => ['East I'       => ['Kissy', 'Calaba Town', 'Wellington']],
                    'West'       => ['West I'       => ['Aberdeen', 'Lumley', 'Goderich']],
                ],
                'Western Area Rural' => [
                    'Koya Rural'     => ['Koya Central'     => ['Waterloo', 'Songo', 'Tombo']],
                    'York Rural'     => ['York Central'     => ['York Village', 'Hamilton', 'Sussex']],
                ],
            ],
            'Northern' => [
                'Bombali' => [
                    'Makari Gbanti' => ['Makeni Town' => ['Teko', 'Rogbaneh', 'Panlap']],
                    'Gbendembu Ngowahun' => ['Gbendembu' => ['Gbendembu', 'Mateboi']],
                ],
                'Tonkolili' => [
                    'Kholifa Rowalla' => ['Magburaka Central' => ['Magburaka', 'Yele', 'Mabonto']],
                ],
            ],
            'Southern' => [
                'Bo' => [
                    'Kakua' => ['Bo Central' => ['Bo Town', 'Kakua Junction', 'Njala']],
                    'Selenga' => ['Selenga Central' => ['Selenga', 'Sembehun']],
                ],
                'Pujehun' => [
                    'Soro Gbema' => ['Soro Gbema Central' => ['Pujehun', 'Sulima']],
                ],
            ],
            'Eastern' => [
                'Kenema' => [
                    'Nongowa' => ['Kenema Central' => ['Kenema Town', 'Blama', 'Hangha']],
                    'Lower Bambara' => ['Lower Bambara Central' => ['Panguma', 'Boajibu']],
                ],
                'Kailahun' => [
                    'Luawa' => ['Luawa Central' => ['Kailahun', 'Daru', 'Segbwema']],
                ],
            ],
            'North-Western' => [
                'Port Loko' => [
                    'BKM' => ['BKM Central' => ['Port Loko Town', 'Lunsar', 'Masiaka']],
                ],
                'Kambia' => [
                    'Magbema' => ['Magbema Central' => ['Kambia Town', 'Rokupr']],
                ],
            ],
        ];

        $regions = $districts = $chiefdoms = $sections = $localities = [];

        // Ensure we have a country row because region.country_id is required.
        $countryId = DB::table('country')->where('name', 'Sierra Leone')->value('id')
            ?? DB::table('country')->insertGetId([
                'name' => 'Sierra Leone',
                'iso_code' => 'SLE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ($structure as $regionName => $districtMap) {
            $region = Region::firstOrCreate(['name' => $regionName], ['country_id' => $countryId]);
            $regions[$regionName] = $region;

            foreach ($districtMap as $districtName => $chiefdomMap) {
                $district = District::firstOrCreate(
                    ['name' => $districtName],
                    ['region_id' => $region->id],
                );
                $districts[$districtName] = $district;

                foreach ($chiefdomMap as $chiefdomName => $sectionMap) {
                    $chiefdom = Chiefdom::firstOrCreate(
                        ['name' => $chiefdomName, 'district_id' => $district->id],
                    );
                    $chiefdoms[$chiefdomName] = $chiefdom;

                    foreach ($sectionMap as $sectionName => $localityList) {
                        $section = Section::firstOrCreate(
                            ['name' => $sectionName, 'chiefdom_id' => $chiefdom->id],
                        );
                        $sections[$sectionName] = $section;

                        foreach ($localityList as $localityName) {
                            $locality = Locality::firstOrCreate(
                                ['name' => $localityName, 'section_id' => $section->id],
                            );
                            $localities[$localityName] = $locality;
                        }
                    }
                }
            }
        }

        return [$regions, $districts, $chiefdoms, $sections, $localities];
    }

    /**
     * @return array{0: array<string, GrievanceType>, 1: array<string, HowReported>, 2: array<string, Priority>}
     */
    private function seedLookups(): array
    {
        $typeNames = [
            'Service Delivery', 'Land Dispute', 'Corruption', 'Discrimination',
            'Environmental', 'Employment', 'Gender-Based Violence',
            'Infrastructure', 'Health Services', 'Education',
        ];
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
        $names = [
            'Ministry of Health'        => 'MoHS',
            'Ministry of Education'     => 'MBSSE',
            'Ministry of Water Resources' => 'MWR',
            'Local Council - Freetown'  => 'FCC',
            'Anti-Corruption Commission' => 'ACC',
            'Sierra Leone Police'       => 'SLP',
        ];
        $out = [];
        foreach ($names as $name => $acronym) {
            $out[$name] = Organization::firstOrCreate(['name' => $name], ['acronym' => $acronym]);
        }

        $this->seedDemoProgrammes($out);

        return $out;
    }

    /** @param  array<string, Organization>  $orgs */
    private function seedDemoProgrammes(array $orgs): void
    {
        $entries = [
            'Ministry of Health' => [
                ['Free Healthcare Initiative', 'FHI', 'active'],
                ['Community Health Worker Programme', 'CHWP', 'active'],
                ['National Immunisation Programme', 'NIP', 'closed'],
            ],
            'Local Council - Freetown' => [
                ['Consumer Protection Programme', 'CPP', 'active'],
                ['Market Surveillance Initiative', 'MSI', 'active'],
                ['Price Monitoring Scheme', 'PMS', 'active'],
            ],
        ];

        foreach ($entries as $orgName => $rows) {
            $org = $orgs[$orgName] ?? null;
            if ($org === null) {
                continue;
            }
            foreach ($rows as [$name, $acronym, $status]) {
                \App\Domain\Organization\Models\Programme::firstOrCreate(
                    ['organization_id' => $org->id, 'name' => $name],
                    [
                        'acronym' => $acronym,
                        'status' => $status,
                        'active' => $status === 'active',
                    ],
                );
            }
        }
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
        array $regions, array $districts, array $chiefdoms, array $sections, array $localities,
        array $types, array $howReported, array $priorities, array $orgs,
    ): void {
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
                'Infrastructure', 'High', GrievanceState::InProgress, 'Kissy',
            ],
            [
                'Teacher salaries delayed at Bo Government Primary for the third month',
                'Teachers at Bo Government Primary School have not been paid for three consecutive months. Staff are threatening to strike. Impacts approximately 600 children.',
                'Employment', 'High', GrievanceState::UnderReview, 'Bo Town',
            ],
            [
                'Land boundary dispute between neighbouring farmers in Panguma',
                'Two families are in dispute over a cocoa farm boundary. Mediation attempts by the section chief have failed. Risk of violence escalating.',
                'Land Dispute', 'Medium', GrievanceState::Submitted, 'Panguma',
            ],
            [
                'Allegation: council official demanding bribes for market permits',
                'Traders at the central market report that a specific council official is demanding payments of 200,000 Le to issue monthly permits. This happens over the past 6 weeks.',
                'Corruption', 'High', GrievanceState::InProgress, 'Freetown',
            ],
            [
                'Sewage overflow near primary school in Calaba Town',
                'Untreated sewage is flowing in a drain adjacent to the primary school playground. Children have reported skin rashes. Has been flagged twice to the sanitation office.',
                'Environmental', 'Critical', GrievanceState::InProgress, 'Calaba Town',
            ],
            [
                'Health clinic out of stock of malaria medication',
                'The government health clinic has had no first-line malaria treatment for 2 weeks. Patients are being turned away or asked to purchase privately at inflated prices.',
                'Health Services', 'High', GrievanceState::Resolved, 'Kenema Town',
            ],
            [
                'Road impassable to Mabonto village since heavy rains',
                'The feeder road linking Mabonto to the main highway is completely cut off since 21 August. Farmers cannot bring produce to market. 5 villages affected.',
                'Infrastructure', 'Medium', GrievanceState::Closed, 'Mabonto',
            ],
            [
                'Reports of domestic violence at household in Makeni — police inaction',
                'Neighbours have repeatedly reported incidents at a household. Police have been called 4 times in the last month with no meaningful response. Situation is deteriorating.',
                'Gender-Based Violence', 'Critical', GrievanceState::Escalated, 'Makeni Town',
            ],
            [
                'School building in Pujehun district without functioning latrines',
                'Primary school has 180 pupils but latrines are collapsed and unusable. Girls are dropping out. Construction funds appear to have been disbursed.',
                'Education', 'High', GrievanceState::UnderReview, 'Pujehun',
            ],
            [
                'Discrimination in job hiring at local NGO office',
                'Applicant with required qualifications alleges they were passed over for a position due to ethnicity. Multiple witnesses available.',
                'Discrimination', 'Medium', GrievanceState::Rejected, 'Freetown',
            ],
            [
                'Water quality complaint — Waterloo — smell and colour',
                'Tap water in Waterloo has been discoloured and smelly since last Tuesday. Several households report stomach illness. Water company has not responded.',
                'Environmental', 'High', GrievanceState::InProgress, 'Waterloo',
            ],
            [
                'Overloading and dangerous driving by commercial vehicles on Magburaka road',
                'Drivers on the Magburaka-Makeni road regularly overload minibuses with 18+ passengers. Two accidents in the past month. Enforcement absent.',
                'Service Delivery', 'Medium', GrievanceState::Submitted, 'Magburaka',
            ],
            [
                'Alleged misuse of school feeding programme stocks in Kailahun',
                'Community monitors report that rice and oil intended for the school feeding programme is being sold in the market. Records need to be audited.',
                'Corruption', 'High', GrievanceState::InProgress, 'Kailahun',
            ],
            [
                'Unpermitted tree felling near community watershed in Gbendembu',
                'Loggers are operating near the stream that is the main water source for 3 villages. They claim to have permits but none have been shown.',
                'Environmental', 'High', GrievanceState::UnderReview, 'Gbendembu',
            ],
            [
                'No antenatal care services at Tombo health post for 3 weeks',
                'The midwife has been reassigned and no replacement sent. Pregnant women travel 30km for antenatal visits. 40+ women currently affected.',
                'Health Services', 'Critical', GrievanceState::InProgress, 'Tombo',
            ],
            [
                'Employment contract not honoured after 6 months',
                'Worker was hired under a written contract by a private company but has received only partial payments. Company claims cash flow issues.',
                'Employment', 'Low', GrievanceState::Closed, 'Port Loko Town',
            ],
            [
                'Market stall demolition in Kambia without notice',
                'Council demolished 20+ market stalls last week without prior notice to vendors. Loss of livelihoods. Vendors claim compensation was promised but never paid.',
                'Service Delivery', 'High', GrievanceState::Resolved, 'Kambia Town',
            ],
            [
                'Abandoned construction project in Lumley — safety hazard',
                'Half-built hotel abandoned 2 years ago is now used by children as a play area. Steel rebar exposed, open pits. Accident waiting to happen.',
                'Infrastructure', 'Medium', GrievanceState::Trashed, 'Lumley',
            ],
            [
                'Dispute over paramount chief election in Luawa',
                'Two factions claim victory in the recent paramount chief election. Tensions high. Traditional leaders council has not convened.',
                'Land Dispute', 'High', GrievanceState::InProgress, 'Kailahun',
            ],
            [
                'School fees still being charged at public school in Goderich',
                'Government policy states no fees at public primary. Headteacher charges 50,000 Le per term claiming "development fee". Families unable to pay keeping children at home.',
                'Education', 'Medium', GrievanceState::UnderReview, 'Goderich',
            ],
        ];

        $admin = User::where('username', 'admin')->first();

        foreach ($templates as $i => [$summary, $description, $typeName, $priorityName, $state, $localityName]) {
            $locality = $localities[$localityName] ?? null;
            $section = $locality?->section;
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
            // workflow rule; the rest route to the relevant ministry/council.
            // Cases still in intake (Submitted/UnderReview) have no
            // classification yet — ACC will assign it.
            $typeToOrg = [
                'Corruption' => 'Anti-Corruption Commission',
                'Gender-Based Violence' => 'Anti-Corruption Commission',
                'Health Services' => 'Ministry of Health',
                'Education' => 'Ministry of Education',
                'Environmental' => 'Ministry of Water Resources',
                'Infrastructure' => 'Local Council - Freetown',
                'Service Delivery' => 'Local Council - Freetown',
                'Employment' => 'Local Council - Freetown',
                'Discrimination' => 'Anti-Corruption Commission',
                'Land Dispute' => 'Local Council - Freetown',
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
            if (in_array($typeName, ['Corruption', 'Gender-Based Violence', 'Discrimination', 'Employment'], true)) {
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
