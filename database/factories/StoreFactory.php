<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Cafe',
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'timezone' => 'Europe/Prague',
            'is_active' => true,
        ];
    }
}
