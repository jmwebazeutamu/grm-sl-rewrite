<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Locality> */
class LocalityFactory extends Factory
{
    protected $model = Locality::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'section_id' => Section::factory(),
        ];
    }
}
