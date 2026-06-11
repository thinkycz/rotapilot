<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->callOnce(UserSeeder::class);
        $this->callOnce(StoreSeeder::class);
        $this->callOnce(StoreBusinessHourSeeder::class);
        $this->callOnce(StoreManagerStoreSeeder::class);
        $this->callOnce(EmployeeSeeder::class);
        $this->callOnce(EmployeeStoreSeeder::class);
        $this->callOnce(EmployeeAvailabilitySeeder::class);
        $this->callOnce(ScheduleSeeder::class);
    }
}
