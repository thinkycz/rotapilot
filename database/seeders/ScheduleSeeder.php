<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ShiftSourceEnum;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Thinkycz\LaravelCore\Support\Config;

class ScheduleSeeder extends Seeder
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
        if ($actorId === null) {
            return;
        }

        $store = Store::query()->first();
        if (!$store instanceof Store) {
            return;
        }
        $storeId = $store->getKey();

        $employees = EmployeeProfile::query()->get();
        if ($employees->count() < 4) {
            return;
        }
        $employeeIds = [];
        foreach ($employees as $emp) {
            $employeeIds[] = $emp->getKey();
        }

        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        $schedule = Schedule::query()->updateOrCreate(
            ['store_id' => $storeId, 'period_start' => $start->format('Y-m-d'), 'period_end' => $end->format('Y-m-d')],
            [
                'name' => 'Week of ' . $start->format('Y-m-d'),
                'status' => 'draft',
                'created_by' => $actorId,
            ],
        );
        $scheduleId = $schedule->getKey();

        $rows = [
            ['date' => $start->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '18:00', 'required_employee_count' => 1],
            ['date' => $start->copy()->addDay()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '18:00', 'required_employee_count' => 1],
            ['date' => $start->copy()->addDays(2)->format('Y-m-d'), 'start_time' => '11:00', 'end_time' => '20:00', 'required_employee_count' => 2],
            ['date' => $start->copy()->addDays(3)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '18:00', 'required_employee_count' => 2],
            ['date' => $start->copy()->addDays(4)->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '21:00', 'required_employee_count' => 3],
            ['date' => $start->copy()->addDays(5)->format('Y-m-d'), 'start_time' => '06:00', 'end_time' => '14:00', 'required_employee_count' => 1],
            ['date' => $start->copy()->addDays(6)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '18:00', 'required_employee_count' => 1],
        ];

        foreach ($rows as $row) {
            ShiftRequirement::query()->updateOrCreate(
                ['schedule_id' => $scheduleId, 'date' => $row['date'], 'start_time' => $row['start_time']],
                $row + [
                    'store_id' => $storeId,
                    'end_time' => $row['end_time'],
                    'source' => ShiftSourceEnum::Manual->value,
                    'created_by' => $actorId,
                ],
            );
        }

        $shiftRows = ShiftRequirement::query()
            ->where('schedule_id', $scheduleId)
            ->orderBy('id')
            ->limit(4)
            ->get();

        foreach ($shiftRows as $idx => $shiftRow) {
            \App\Models\ShiftAssignment::query()->updateOrCreate(
                [
                    'shift_requirement_id' => $shiftRow->getKey(),
                    'employee_profile_id' => $employeeIds[$idx % \count($employeeIds)],
                ],
                [
                    'assigned_by' => $actorId,
                    'status' => 'assigned',
                ],
            );
        }

        \App\Models\ScheduleConflict::query()->where('schedule_id', $scheduleId)->delete();
    }
}
