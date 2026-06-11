<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftAssignmentStoreController
{
    use ValidatesWebRequests;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly ConflictDetectionService $conflicts,
    ) {}

    /**
     * Assign an employee to a shift.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $reqId = (int) $request->query('shift_requirement_id', '0');
        $row = ShiftRequirement::query()->getQuery()->getQuery()->where('id', $reqId)->first();
        if ($row === null) {
            \abort(404);
        }
        $req = Db::hydrateOne($row, ShiftRequirement::class);
        if ($req === null) {
            \abort(404);
        }

        $scheduleRow = Schedule::query()->getQuery()->getQuery()->where('id', $req->getScheduleId())->first();
        if ($scheduleRow === null) {
            \abort(404);
        }
        $schedule = Db::hydrateOne($scheduleRow, Schedule::class);
        if ($schedule === null) {
            \abort(404);
        }

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

        $scheduleRow2 = Schedule::query()->getQuery()->getQuery()->where('id', $req->getScheduleId())->first();
        if ($scheduleRow2 !== null) {
            $schedule2 = Db::hydrateOne($scheduleRow2, Schedule::class);
            if ($schedule2 !== null) {
                $this->conflicts->recompute($schedule2);
            }
        }

        $request->session()->flash('success', \__('Employee assigned.'));

        return \back();
    }
}
