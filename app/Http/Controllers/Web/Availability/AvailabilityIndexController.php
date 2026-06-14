<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Availability;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeAvailability;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityIndexController
{
    use ValidatesWebRequests;

    /**
     * Page size for the index view.
     */
    public const int TAKE = 25;

    /**
     * Show the availability grid for a month.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        Authorization::mustBeStoreManager($user);

        $monthVal = $request->query('month');
        $month = \is_string($monthVal) ? $monthVal : \now()->format('Y-m');
        $storeIdVal = $request->query('store_id');
        $storeId = \is_numeric($storeIdVal) ? (int) $storeIdVal : 0;
        $employeeIdVal = $request->query('employee_id');
        $employeeId = \is_numeric($employeeIdVal) ? (int) $employeeIdVal : 0;

        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->format('Y-m-d');
        }

        $employeeList = Authorization::managedEmployeesQuery($user)->orderBy('name')->get();

        $stores = Authorization::managedStores($user);

        $employeeIds = [];
        foreach ($employeeList as $e) {
            $employeeIds[] = $e->getKey();
        }

        $availabilities = EmployeeAvailability::query()
            ->whereIn('employee_profile_id', $employeeIds === [] ? [0] : $employeeIds)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        $byEmployeeDay = [];
        foreach ($availabilities as $row) {
            $empId = $row->getEmployeeProfileId();
            $date = $row->getDate();
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
                    'id' => $entry->getKey(),
                    'type' => $entry->getType()->value,
                    'start_time' => $entry->getStartTime(),
                    'end_time' => $entry->getEndTime(),
                    'note' => $entry->getNote(),
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
