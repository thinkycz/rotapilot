<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShiftAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 */
class ShiftAssignmentFactory extends Factory
{
    protected $model = ShiftAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_requirement_id' => ShiftRequirementFactory::new(),
            'employee_profile_id' => EmployeeProfileFactory::new(),
            'status' => 'draft',
            'source' => 'manual',
            'assigned_by' => UserFactory::new(),
            'note' => $this->faker->sentence(),
        ];
    }
}
