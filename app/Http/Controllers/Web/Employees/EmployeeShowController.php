<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeShowController
{
    /**
     * Show a single employee.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $employee = ModelFinder::findOrAbort(EmployeeProfile::class, $id);

        Authorization::mustViewEmployee($user, $employee);

        $employee->loadMissing('user');
        $login = $employee->getUser();
        $storeList = $employee->stores()->orderBy('name')->get();

        return Inertia::render('employees/Show', [
            'employee' => [
                'id' => $employee->getKey(),
                'name' => $employee->getName(),
                'email' => $employee->getEmail(),
                'phone' => $employee->getPhone(),
                'role_label' => $employee->getRoleLabel(),
                'max_hours_per_week' => $employee->getMaxHoursPerWeek(),
                'hourly_rate' => $employee->getHourlyRate(),
                'is_active' => $employee->getIsActive(),
                'has_login' => $employee->hasLoginAccount(),
                'login' => $login instanceof User ? [
                    'id' => $login->getKey(),
                    'email' => $login->getEmail(),
                    'locale' => $login->getLocale(),
                ] : null,
                'public_schedule_url' => '/public/employee-schedules?token=' . $employee->ensurePublicScheduleToken(),
            ],
            'stores' => $storeList->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
        ]);
    }
}
