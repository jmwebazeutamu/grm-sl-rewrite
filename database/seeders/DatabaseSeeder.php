<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Stable reference data — same for every environment, belongs in the
        // baked image. Demo users / sample grievances live in DemoDataSeeder
        // and should be invoked explicitly (e.g. `db:seed --class=...`).
        $this->call([
            RolePermissionSeeder::class,
            GeographySeeder::class,
            SuperAdminSeeder::class,
            ProgrammeSeeder::class,
        ]);
    }
}
