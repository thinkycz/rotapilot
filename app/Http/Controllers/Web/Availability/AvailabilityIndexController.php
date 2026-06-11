<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Availability;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityIndexController
{
    use ValidatesWebRequests;

    /**
     * Show the availability grid for a month.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $month = (string) $request->query('month', \now()->format('Y-m'));
        $storeId = (int) $request->query('store_id', '0');
        $employeeId = (int) $request->query('employee_id', '0');

        $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->format('Y-m-d');
        }

        $employeesQuery = Authorization::managedEmployeesQuery($user);
        $employees = $employeesQuery->getQuery()->orderBy('name')->get();
        $employeeList = Db::hydrate($employees, EmployeeProfile::class);

        $stores = Authorization::managedStores($user);

        $employeeIds = [];
        foreach ($employeeList as $e) {
            $employeeIds[] = $e->getKey();
        }

        $availabilities = EmployeeAvailability::query()
            ->getQuery()
            ->whereIn('employee_profile_id', $employeeIds ?: [0])
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        $byEmployeeDay = [];
        foreach ($availabilities as $row) {
            $empId = (int) $row->employee_profile_id;
            $date = (string) $row->date;
            $byEmployeeDay[$empId][$date] = $row;
        }

        $grid = [];
        foreach ($employeeList as $e) {
            $empId = $e->getKey();
            $row = [
                'employee' => [
                    'id' => $empId,
                    'name' => $e->getName(),
                ],
                'days' => [],
            ];
            foreach ($days as $d) {
                $entry = $byEmployeeDay[$empId][$d] ?? null;
                $row['days'][$d] = $entry === null ? null : [
                    'id' => (int) $entry->id,
                    'type' => (string) $entry->type,
                    'start_time' => $entry->start_time !== null ? (string) $entry->start_time : null,
                    'end_time' => $entry->end_time !== null ? (string) $entry->end_time : null,
                ];
            }
            $grid[] = $row;
        }

        return Inertia::render('availability/Index', [
            'month' => $month,
            'days' => $days,
            'employees' => $grid,
            'stores' => $stores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
            'filter_store_id' => $storeId,
            'filter_employee_id' => $employeeId,
        ]);
    }
}
