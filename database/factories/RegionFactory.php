<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Locality\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/** @extends Factory<Region> */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $countryId = DB::table('country')->where('name', 'Sierra Leone')->value('id')
            ?? DB::table('country')->insertGetId([
                'name' => 'Sierra Leone',
                'iso_code' => 'SLE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'name' => fake()->unique()->state(),
            'country_id' => $countryId,
        ];
    }
}
