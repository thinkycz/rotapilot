<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::now()->addDays(7)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        return [
            'store_id' => StoreFactory::new(),
            'name' => 'Weekly Schedule',
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
            'status' => 'draft',
            'created_by' => UserFactory::new(),
            'published_at' => null,
        ];
    }
}
