<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Thinkycz\LaravelCore\Support\Config;

class EmployeeStoreSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Config::inject()->appEnvIs(['staging', 'production'])) {
            return;
        }

        $employees = EmployeeProfile::query()->get();
        $stores = Store::query()->orderBy('id')->get();

        if ($stores->isEmpty() || $employees->isEmpty()) {
            return;
        }

        foreach ($employees as $idx => $employee) {
            $primary = $stores->get($idx % $stores->count());
            $secondary = $stores->get(($idx + 1) % $stores->count());

            if ($primary instanceof Store && $secondary instanceof Store) {
                $employee->stores()->syncWithoutDetaching([
                    $primary->getKey(),
                    $secondary->getKey(),
                ]);
            }
        }
    }
}
