<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShiftRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ShiftRequirement>
 */
class ShiftRequirementFactory extends Factory
{
    protected $model = ShiftRequirement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => ScheduleFactory::new(),
            'store_id' => StoreFactory::new(),
            'date' => Carbon::now()->addDays(7)->format('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'role_label' => 'Barista',
            'note' => $this->faker->sentence(),
            'source' => 'manual',
            'created_by' => UserFactory::new(),
        ];
    }
}
