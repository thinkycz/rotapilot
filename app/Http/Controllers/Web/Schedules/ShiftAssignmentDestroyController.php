<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\Db;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftAssignmentDestroyController
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
     * Remove an assignment.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $assignment = ModelFinder::findOrAbort(ShiftAssignment::class, $id);

        $req = $assignment->getShiftRequirement();
        if ($req !== null) {
            $scheduleRow = Schedule::query()->getQuery()->getQuery()->where('id', $req->getScheduleId())->first();
            if ($scheduleRow !== null) {
                $schedule = Db::hydrateOne($scheduleRow, Schedule::class);
                if ($schedule !== null && !Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
                    \abort(403);
                }
            }
        }

        $this->assignments->unassign($assignment);

        $request->session()->flash('success', \__('Assignment removed.'));

        return \back();
    }
}
