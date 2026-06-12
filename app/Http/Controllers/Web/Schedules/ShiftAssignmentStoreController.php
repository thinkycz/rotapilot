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
        $reqIdRaw = $request->input('shift_requirement_id');
        $reqId = \is_scalar($reqIdRaw) ? (int) $reqIdRaw : 0;
        $req = ModelFinder::findOrAbort(ShiftRequirement::class, $reqId);

        $schedule = ModelFinder::findOrAbort(Schedule::class, $req->getScheduleId());

        $actor = User::mustAuth();
        if (!Authorization::canManageSchedule($actor, $schedule)) {
            \abort(403);
        }

        $validated = $this->validateRequest($request, [
            'employee_profile_id' => 'required|integer|exists:employee_profiles,id',
        ]);

        $employee = EmployeeProfile::query()->find($validated->assertInt('employee_profile_id'));
        if (!$employee instanceof EmployeeProfile) {
            \abort(404);
        }

        Authorization::mustViewEmployee($actor, $employee);

        $this->assignments->assign($req, $employee, $actor);

        $request->session()->flash('success', \__('Employee assigned.'));

        return \back();
    }
}
