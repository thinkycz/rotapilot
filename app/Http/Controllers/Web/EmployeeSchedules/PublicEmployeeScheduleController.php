<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\EmployeeSchedules;

use App\Models\EmployeeProfile;
use App\Support\EmployeeScheduleView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicEmployeeScheduleController
{
    /**
     * Show public schedules for the stores assigned to an employee.
     */
    public function __invoke(Request $request): Response
    {
        $tokenRaw = $request->query('token');
        $token = \is_string($tokenRaw) ? $tokenRaw : '';
        if ($token === '') {
            \abort(404);
        }

        $employee = EmployeeProfile::query()
            ->where('public_schedule_token', $token)
            ->first();
        if (!$employee instanceof EmployeeProfile) {
            \abort(404);
        }

        $view = EmployeeScheduleView::build($employee, $request);

        return Inertia::render('public/EmployeeSchedules', [
            'employee' => [
                'id' => $employee->getKey(),
                'name' => $employee->getName(),
            ],
            'token' => $token,
            ...$view,
        ]);
    }
}
