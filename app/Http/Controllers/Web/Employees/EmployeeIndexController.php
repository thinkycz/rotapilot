<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeIndexController
{
    use ValidatesWebRequests;

    /**
     * Page size for the index view.
     */
    public const int TAKE = 25;

    /**
     * Show the employees list.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $rows = Authorization::managedEmployeesQuery($user)
            ->with('user')
            ->orderBy('name')
            ->get();

        return Inertia::render('employees/Index', [
            'employees' => $rows->map(static fn(EmployeeProfile $e): array => [
                'id' => $e->getKey(),
                'name' => $e->getName(),
                'email' => $e->getEmail(),
                'phone' => $e->getPhone(),
                'role_label' => $e->getRoleLabel(),
                'max_hours_per_week' => $e->getMaxHoursPerWeek(),
                'is_active' => $e->getIsActive(),
                'has_login' => $e->hasLoginAccount(),
            ])->values()->all(),
        ]);
    }
}
