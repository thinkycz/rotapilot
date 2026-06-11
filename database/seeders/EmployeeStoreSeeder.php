<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        $employeeRows = EmployeeProfile::query()->getQuery()->get();
        $employees = \App\Support\Db::hydrate($employeeRows, EmployeeProfile::class);
        $storeRows = Store::query()->getQuery()->orderBy('id')->get();
        $stores = \App\Support\Db::hydrate($storeRows, Store::class);

        if ($stores->isEmpty() || $employees->isEmpty()) {
            return;
        }

        $names = $employees->pluck('name')->all();
        foreach ($employees as $idx => $employee) {
            $primary = $stores[$idx % $stores->count()];
            $secondary = $stores[($idx + 1) % $stores->count()];

            DB::table('employee_store')->updateOrInsert(
                ['employee_profile_id' => $employee->getKey(), 'store_id' => $primary->getKey()],
                ['updated_at' => \now(), 'created_at' => \now()],
            );
            if ($secondary->getKey() !== $primary->getKey()) {
                DB::table('employee_store')->updateOrInsert(
                    ['employee_profile_id' => $employee->getKey(), 'store_id' => $secondary->getKey()],
                    ['updated_at' => \now(), 'created_at' => \now()],
                );
            }
        }
    }
}
