<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftAutoFillController
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
     * Auto-fill a shift with the best candidates.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('shift_requirement_id', '0');
        $row = ShiftRequirement::query()->getQuery()->getQuery()->where('id', $id)->first();
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

        $this->assignments->autoFill($req, User::mustAuth());
        $this->conflicts->recompute($schedule);

        $request->session()->flash('success', \__('Shift auto-filled.'));

        return \back();
    }
}
