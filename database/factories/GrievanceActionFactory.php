<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrievanceAction> */
class GrievanceActionFactory extends Factory
{
    protected $model = GrievanceAction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'grievance_id' => Grievance::factory(),
            'type' => ActionType::Update->value,
            'body' => fake()->paragraph(),
        ];
    }
}
