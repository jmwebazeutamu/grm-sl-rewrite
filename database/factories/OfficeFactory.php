<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Office;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Office> */
class OfficeFactory extends Factory
{
    protected $model = Office::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->streetName().' Office',
            'acronym' => null,
            'address' => fake()->address(),
            'is_headquarters' => false,
            'organization_id' => Organization::factory(),
            'region_id' => null,
            'district_id' => null,
        ];
    }

    public function headquarters(): static
    {
        return $this->state(fn () => ['is_headquarters' => true]);
    }
}
