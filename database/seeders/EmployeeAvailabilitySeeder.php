<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Thinkycz\LaravelCore\Support\Config;

class EmployeeAvailabilitySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Config::inject()->appEnvIs(['staging', 'production'])) {
            return;
        }

        $manager = User::query()->where('email', 'manager@example.com')->first();
        $actorId = $manager instanceof User ? $manager->getKey() : null;
        $employees = EmployeeProfile::query()->get();
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        foreach ($employees as $idx => $employee) {
            $offDay = ($idx % 5) + 1; // 1..5 - monday..friday off in rotation
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $dayOfWeek = (int) $d->format('N');
                if ($dayOfWeek === $offDay) {
                    \App\Models\EmployeeAvailability::query()->updateOrCreate(
                        [
                            'employee_profile_id' => $employee->getKey(),
                            'date' => $d->format('Y-m-d'),
                            'type' => 'unavailable',
                        ],
                        [
                            'start_time' => null,
                            'end_time' => null,
                            'note' => 'Day off',
                            'source' => 'manager',
                            'created_by' => $actorId,
                        ],
                    );

                    continue;
                }

                \App\Models\EmployeeAvailability::query()->updateOrCreate(
                    [
                        'employee_profile_id' => $employee->getKey(),
                        'date' => $d->format('Y-m-d'),
                        'type' => 'available',
                    ],
                    [
                        'start_time' => '08:00',
                        'end_time' => '20:00',
                        'note' => null,
                        'source' => 'manager',
                        'created_by' => $actorId,
                    ],
                );
            }
        }
    }
}
