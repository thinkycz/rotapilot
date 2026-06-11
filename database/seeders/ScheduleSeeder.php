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
use Illuminate\Support\Facades\DB;
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

        $adminRow = User::query()->getQuery()->where('email', 'admin@example.com')->first();
        $admin = $adminRow !== null ? \App\Support\Db::hydrateOne($adminRow, User::class) : null;
        $actorId = $admin instanceof User ? $admin->getKey() : null;
        if ($actorId === null) {
            return;
        }

        $storeRow = Store::query()->getQuery()->first();
        $store = $storeRow !== null ? \App\Support\Db::hydrateOne($storeRow, Store::class) : null;
        if (!$store instanceof Store) {
            return;
        }
        $storeId = $store->getKey();

        $employeeRows = EmployeeProfile::query()->getQuery()->get();
        $employees = \App\Support\Db::hydrate($employeeRows, EmployeeProfile::class);
        if ($employees->count() < 4) {
            return;
        }
        $employeeIds = [];
        foreach ($employees as $emp) {
            $employeeIds[] = (int) $emp->id;
        }

        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        DB::table('schedules')->updateOrInsert(
            ['store_id' => $storeId, 'period_start' => $start->format('Y-m-d'), 'period_end' => $end->format('Y-m-d')],
            [
                'name' => 'Week of ' . $start->format('Y-m-d'),
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_at' => \now(),
                'created_at' => \now(),
            ],
        );

        $scheduleRow = Schedule::query()->getQuery()->where('store_id', $storeId)
            ->where('period_start', $start->format('Y-m-d'))
            ->where('period_end', $end->format('Y-m-d'))
            ->first();
        $schedule = $scheduleRow !== null ? \App\Support\Db::hydrateOne($scheduleRow, Schedule::class) : null;
        if (!$schedule instanceof Schedule) {
            return;
        }
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
            DB::table('shift_requirements')->updateOrInsert(
                ['schedule_id' => $scheduleId, 'date' => $row['date'], 'start_time' => $row['start_time']],
                $row + [
                    'store_id' => $storeId,
                    'end_time' => $row['end_time'],
                    'source' => ShiftSourceEnum::Manual->value,
                    'created_by' => $actorId,
                    'updated_at' => \now(),
                    'created_at' => \now(),
                ],
            );
        }

        $shiftRows = ShiftRequirement::query()->getQuery()
            ->where('schedule_id', $scheduleId)
            ->orderBy('id')
            ->limit(4)
            ->get();

        foreach ($shiftRows as $idx => $shiftRow) {
            DB::table('shift_assignments')->updateOrInsert(
                [
                    'shift_requirement_id' => (int) $shiftRow->id,
                    'employee_profile_id' => $employeeIds[$idx % \count($employeeIds)],
                ],
                [
                    'assigned_by' => $actorId,
                    'status' => 'assigned',
                    'updated_at' => \now(),
                    'created_at' => \now(),
                ],
            );
        }

        DB::table('schedule_conflicts')->where('schedule_id', $scheduleId)->delete();
    }
}
