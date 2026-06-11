<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftAssignmentStoreController
{
    use ValidatesWebRequests;

    /**
     * Constructor.
     */
    public function __construct(private readonly AssignmentService $assignments) {}

    /**
     * Assign an employee to a shift.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $reqId = (int) $request->query('shift_requirement_id', '0');
        $req = ModelFinder::findOrAbort(ShiftRequirement::class, $reqId);

        $schedule = ModelFinder::findOrAbort(Schedule::class, $req->getScheduleId());

        if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
            \abort(403);
        }

        $validated = $this->validateRequest($request, [
            'employee_profile_id' => 'required|integer|exists:employee_profiles,id',
        ]);

        $employee = EmployeeProfile::query()->find((int) $validated->mixed('employee_profile_id'));
        if (!$employee instanceof EmployeeProfile) {
            \abort(404);
        }

        $this->assignments->assign($req, $employee, User::mustAuth());

        $request->session()->flash('success', \__('Employee assigned.'));

        return \back();
    }
}
