<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrievanceType> */
class GrievanceTypeFactory extends Factory
{
    protected $model = GrievanceType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
