<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use Illuminate\Database\Seeder;

/**
 * Canonical GRM-SL programmes mapped to their implementing organisations.
 * Stable reference data — shipped in every environment, not demo-only.
 *
 * Idempotent: uses withTrashed so a soft-deleted record with the same
 * (organisation_id, name) is restored rather than duplicated.
 */
class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        // Programmes are run by multiple orgs (NaCSA runs most, FCC and ACC
        // have overlap on Cash for Work, etc.). Kept as (org, programme)
        // pairs because the unique constraint is composite.
        $entries = [
            // NaCSA — social-protection portfolio, runs the full set.
            ['National Commission for Social Action', 'Cash for Work',         'CFW'],
            ['National Commission for Social Action', 'Youth Employment',      'YE'],
            ['National Commission for Social Action', 'PSSNYE',                'PSSNYE'],
            ['National Commission for Social Action', 'Cash Plus',             'CP'],
            ['National Commission for Social Action', 'SSN / Safety Nets',     'SSN'],
            ['National Commission for Social Action', 'Economic Inclusion',    'EI'],
            ['National Commission for Social Action', 'Labor Grievances',      'LG'],
            ['National Commission for Social Action', 'Other',                 'OTH'],

            // Freetown City Council.
            ['Local Council - Freetown',              'Cash for Work',         'CFW'],
            ['Local Council - Freetown',              'Youth Employment',      'YE'],

            // Anti-Corruption Commission — also owns some cash-flow cases.
            ['Anti-Corruption Commission',            'Labor Grievances',      'LG'],
            ['Anti-Corruption Commission',            'Cash for Work',         'CFW'],
            ['Anti-Corruption Commission',            'SSN / Safety Nets',     'SSN'],
            ['Anti-Corruption Commission',            'Other',                 'OTH'],
        ];

        foreach ($entries as [$orgName, $name, $acronym]) {
            $org = Organization::where('name', $orgName)->first();
            if ($org === null) {
                $this->command?->warn("Skipping '{$name}': organisation '{$orgName}' not found. Run DemoDataSeeder or create it first.");

                continue;
            }

            $programme = Programme::withTrashed()->firstOrCreate(
                ['organization_id' => $org->id, 'name' => $name],
                ['acronym' => $acronym, 'status' => 'active', 'active' => true],
            );

            if ($programme->trashed()) {
                $programme->restore();
            }
        }
    }
}
