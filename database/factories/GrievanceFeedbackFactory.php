<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Grievance\Enums\FeedbackRating;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrievanceFeedback> */
class GrievanceFeedbackFactory extends Factory
{
    protected $model = GrievanceFeedback::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'grievance_id' => Grievance::factory(),
            'rating' => FeedbackRating::Satisfied->value,
            'comment' => fake()->optional()->sentence(),
            'channel' => 'web',
            'submitted_at' => now(),
        ];
    }
}
