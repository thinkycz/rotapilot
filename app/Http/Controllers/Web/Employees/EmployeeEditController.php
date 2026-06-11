<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Models\EmployeeProfile;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeEditController
{
    /**
     * Show the edit form.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $row = EmployeeProfile::query()->getQuery()->where('id', $id)->first();
        if ($row === null) {
            \abort(404);
        }
        $employee = Db::hydrateOne($row, EmployeeProfile::class);
        if ($employee === null) {
            \abort(404);
        }

        $stores = Authorization::managedStores($user);

        return Inertia::render('employees/Edit', [
            'employee' => [
                'id' => $employee->getKey(),
                'name' => $employee->getName(),
                'email' => $employee->getEmail(),
                'phone' => $employee->getPhone(),
                'role_label' => $employee->getRoleLabel(),
                'max_hours_per_week' => $employee->getMaxHoursPerWeek(),
                'is_active' => $employee->getIsActive(),
            ],
            'stores' => $stores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
        ]);
    }
}
