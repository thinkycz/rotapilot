<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
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

        $employee->loadMissing('user');

        Authorization::mustViewEmployee($user, $employee);

        $storeRows = $employee->stores()->getQuery()->orderBy('name')->get();
        $storeList = Db::hydrate($storeRows, \App\Models\Store::class);

        return Inertia::render('employees/Show', [
            'employee' => [
                'id' => $employee->getKey(),
                'name' => $employee->getName(),
                'email' => $employee->getEmail(),
                'phone' => $employee->getPhone(),
                'role_label' => $employee->getRoleLabel(),
                'max_hours_per_week' => $employee->getMaxHoursPerWeek(),
                'is_active' => $employee->getIsActive(),
                'has_login' => $employee->hasLoginAccount(),
            ],
            'stores' => $storeList->map(static fn(\App\Models\Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
        ]);
    }
}
