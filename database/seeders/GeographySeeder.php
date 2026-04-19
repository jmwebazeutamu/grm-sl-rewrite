<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Region;
use App\Domain\Locality\Models\Section;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/geography.json');

        if (! file_exists($path)) {
            $this->command?->error('geography.json not found at '.$path);
            $this->command?->error('Run: docker cp /tmp/geography.json grm-sl:/app/database/seeders/geography.json');

            return;
        }

        $rows = json_decode((string) file_get_contents($path), true) ?? [];
        $this->command?->info('Seeding '.count($rows).' geography rows...');

        $countryId = (int) \DB::table('country')
            ->where('iso_code', 'SLE')
            ->value('id');
        if ($countryId === 0) {
            $countryId = (int) \DB::table('country')->insertGetId([
                'name' => 'Sierra Leone',
                'iso_code' => 'SLE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($rows as $row) {
            $region = Region::firstOrCreate(
                ['name' => $row['region']],
                ['country_id' => $countryId],
            );
            $district = District::firstOrCreate([
                'name' => $row['district'],
                'region_id' => $region->id,
            ]);
            $chiefdom = Chiefdom::firstOrCreate([
                'name' => $row['chiefdom'],
                'district_id' => $district->id,
            ]);
            $section = Section::firstOrCreate([
                'name' => $row['section'],
                'chiefdom_id' => $chiefdom->id,
            ]);
            Locality::firstOrCreate([
                'name' => $row['locality'],
                'section_id' => $section->id,
            ]);
        }

        $this->command?->info('Done. Counts:');
        $this->command?->info('  Regions:    '.Region::count().' (expected 5)');
        $this->command?->info('  Districts:  '.District::count().' (expected 16)');
        $this->command?->info('  Chiefdoms:  '.Chiefdom::count().' (expected 131)');
        $this->command?->info('  Sections:   '.Section::count().' (expected 265)');
        $this->command?->info('  Localities: '.Locality::count().' (expected 319)');
    }
}
