<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<District> */
class DistrictFactory extends Factory
{
    protected $model = District::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'region_id' => Region::factory(),
        ];
    }
}
