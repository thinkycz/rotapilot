<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use App\Models\StoreBusinessHour;
use Illuminate\Database\Seeder;
use Thinkycz\LaravelCore\Support\Config;

class StoreBusinessHourSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Config::inject()->appEnvIs(['staging', 'production'])) {
            return;
        }

        $stores = Store::query()->get();

        foreach ($stores as $store) {
            for ($day = 1; $day <= 7; ++$day) {
                $isWeekend = $day === 6 || $day === 7;
                StoreBusinessHour::query()->updateOrCreate(
                    ['store_id' => $store->getKey(), 'day_of_week' => $day],
                    [
                        'opens_at' => $isWeekend ? '10:00' : '08:00',
                        'closes_at' => $isWeekend ? '22:00' : '20:00',
                        'is_closed' => false,
                    ],
                );
            }
        }
    }
}
