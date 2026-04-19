<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Grievance> */
class GrievanceFactory extends Factory
{
    protected $model = Grievance::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'g_number' => sprintf('GRM-%d-%06d', now()->year, $seq),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'grievance_type_id' => GrievanceType::factory(),
            'state' => GrievanceState::Submitted->value,
            'is_anonymous' => false,
            'received_at' => now(),
        ];
    }

    public function inState(GrievanceState $state): static
    {
        return $this->state(fn () => ['state' => $state->value]);
    }

    public function anonymous(): static
    {
        return $this->state(fn () => ['is_anonymous' => true]);
    }
}
