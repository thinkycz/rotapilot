<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftAssignmentValidity;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

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

        $validity = ShiftAssignmentValidity::inject();
        $validated = $this->validateRequest($request, [
            'employee_profile_id' => $validity->employeeProfileId()->required()->toArray(),
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
        ]);

        $employee = EmployeeProfile::query()->find($validated->assertInt('employee_profile_id'));
        if (!$employee instanceof EmployeeProfile) {
            \abort(404);
        }

        Authorization::mustViewEmployee($actor, $employee);

        $startTime = $validated->assertString('start_time');
        $endTime = $validated->assertString('end_time');

        $reqStart = \mb_substr($req->getStartTime(), 0, 5);
        $reqEnd = \mb_substr($req->getEndTime(), 0, 5);

        if ($startTime < $reqStart || $endTime > $reqEnd) {
            Thrower::default()
                ->message('start_time', Typer::assertString(\__('The assignment time must be within the shift business hours (:start - :end).', [
                    'start' => $reqStart,
                    'end' => $reqEnd,
                ])))
                ->throw();
        }

        if ($startTime >= $endTime) {
            Thrower::default()
                ->message('start_time', Typer::assertString(\__('The start time must be before the end time.')))
                ->throw();
        }

        $this->assignments->assign($req, $employee, $actor, $startTime, $endTime);

        $request->session()->flash('shift_modal_success', \__('Employee assigned.'));

        return \back();
    }
}
