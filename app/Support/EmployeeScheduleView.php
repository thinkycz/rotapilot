<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EmployeeScheduleView
{
    /**
     * Build the read-only schedule view for an employee's assigned stores.
     *
     * @return array{stores: array<int, array{id: int, name: string}>, selected_store_id: int|null, schedules: array<int, array<string, mixed>>, selected_schedule: array<string, mixed>|null, days: array<string, array{shifts: array<int, array<string, mixed>>}>}
     */
    public static function build(EmployeeProfile $employee, Request $request): array
    {
        $stores = $employee->stores()->orderBy('name')->get();
        $storeIds = [];
        foreach ($stores as $store) {
            $storeIds[] = $store->getKey();
        }

        $selectedStoreId = self::selectedId($request, 'store_id', $storeIds);
        $schedules = [];
        $selectedSchedule = null;
        $days = [];

        if ($selectedStoreId !== null) {
            $scheduleRows = Schedule::query()
                ->where('store_id', $selectedStoreId)
                ->where('status', 'published')
                ->where('period_start', '>=', CarbonImmutable::now()->startOfMonth()->format('Y-m-d'))
                ->orderBy('period_start')
                ->get();

            $scheduleIds = [];
            foreach ($scheduleRows as $schedule) {
                $scheduleIds[] = $schedule->getKey();
                $schedules[] = self::scheduleRow($schedule);
            }

            $selectedScheduleId = self::selectedId($request, 'schedule_id', $scheduleIds);
            foreach ($scheduleRows as $schedule) {
                if ($selectedScheduleId === null || $selectedScheduleId === $schedule->getKey()) {
                    $selectedSchedule = $schedule;
                    break;
                }
            }

            if ($selectedSchedule instanceof Schedule) {
                $days = self::days($selectedSchedule);
            }
        }

        return [
            'stores' => $stores->map(static fn(Store $store): array => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ])->values()->all(),
            'selected_store_id' => $selectedStoreId,
            'schedules' => $schedules,
            'selected_schedule' => $selectedSchedule instanceof Schedule ? self::scheduleRow($selectedSchedule) : null,
            'days' => $days,
        ];
    }

    /**
     * Resolve a requested id against an allow-list.
     *
     * @param array<int, int> $allowedIds
     */
    private static function selectedId(Request $request, string $key, array $allowedIds): int|null
    {
        if (\count($allowedIds) === 0) {
            return null;
        }

        $raw = $request->query($key);
        $id = \is_scalar($raw) ? (int) $raw : 0;

        if (\in_array($id, $allowedIds, true)) {
            return $id;
        }

        return $allowedIds[0];
    }

    /**
     * Serialize a schedule row.
     *
     * @return array<string, mixed>
     */
    private static function scheduleRow(Schedule $schedule): array
    {
        return [
            'id' => $schedule->getKey(),
            'name' => $schedule->getName(),
            'period_start' => $schedule->getPeriodStart(),
            'period_end' => $schedule->getPeriodEnd(),
        ];
    }

    /**
     * Serialize days and shifts for the selected schedule.
     *
     * @return array<string, array{shifts: array<int, array<string, mixed>>}>
     */
    private static function days(Schedule $schedule): array
    {
        $schedule->loadMissing('shiftRequirements');

        $start = CarbonImmutable::parse($schedule->getPeriodStart());
        $end = CarbonImmutable::parse($schedule->getPeriodEnd());
        $days = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $days[$date->format('Y-m-d')] = ['shifts' => []];
        }

        foreach ($schedule->getShiftRequirements() as $requirement) {
            $date = $requirement->getDate();
            if (!isset($days[$date])) {
                continue;
            }

            $days[$date]['shifts'][] = [
                'id' => $requirement->getKey(),
                'start_time' => $requirement->getStartTime(),
                'end_time' => $requirement->getEndTime(),
                'role_label' => $requirement->getRoleLabel(),
                'note' => $requirement->getNote(),
                'assignments' => $requirement->assignments()
                    ->with('employeeProfile')
                    ->tap(static fn($query) => ShiftAssignment::scopeActive($query))
                    ->orderBy('start_time')
                    ->get()
                    ->map(static fn(ShiftAssignment $assignment): array => [
                        'id' => $assignment->getKey(),
                        'employee_name' => $assignment->getEmployeeProfile()->getName(),
                        'start_time' => $assignment->getStartTime(),
                        'end_time' => $assignment->getEndTime(),
                    ])->values()->all(),
            ];
        }

        \ksort($days);

        return $days;
    }
}
