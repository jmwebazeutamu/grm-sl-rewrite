<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->optional()->safeEmail(),
            'mobile_number' => fake()->optional()->e164PhoneNumber(),
            'office_number' => null,
            'organization_id' => Organization::factory(),
            'office_id' => null,
        ];
    }
}
