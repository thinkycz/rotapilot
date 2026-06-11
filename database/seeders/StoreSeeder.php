<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;
use Thinkycz\LaravelCore\Support\Config;

class StoreSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Config::inject()->appEnvIs(['staging', 'production'])) {
            return;
        }

        $data = [
            ['name' => 'Downtown Cafe', 'address' => '1 Main Street', 'city' => 'Prague', 'timezone' => 'Europe/Prague'],
            ['name' => 'Mall Kiosk', 'address' => 'Westfield Mall', 'city' => 'Prague', 'timezone' => 'Europe/Prague'],
            ['name' => 'Airport Outlet', 'address' => 'Vaclav Havel Apt', 'city' => 'Prague', 'timezone' => 'Europe/Prague'],
        ];

        foreach ($data as $row) {
            Store::query()->getQuery()->updateOrInsert(
                ['name' => $row['name']],
                $row + ['is_active' => true, 'updated_at' => \now(), 'created_at' => \now()],
            );
        }
    }
}
