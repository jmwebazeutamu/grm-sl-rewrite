<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Section> */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'chiefdom_id' => Chiefdom::factory(),
        ];
    }
}
