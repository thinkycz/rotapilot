<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Calendar;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\EmployeeScheduleView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyCalendarController
{
    /**
     * Show the logged-in employee's own published shifts.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $user->loadMissing('employeeProfile');
        $profile = $user->employeeProfile;
        if (!$profile instanceof EmployeeProfile) {
            return Inertia::render('calendar/Mine', [
                'has_profile' => false,
                'stores' => [],
                'selected_store_id' => null,
                'schedules' => [],
                'selected_schedule' => null,
                'days' => [],
            ]);
        }

        return Inertia::render('calendar/Mine', [
            'has_profile' => true,
            ...EmployeeScheduleView::build($profile, $request),
        ]);
    }
}
