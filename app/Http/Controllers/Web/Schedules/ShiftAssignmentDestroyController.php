<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Support\Authorization;
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
    ) {}

    /**
     * Remove an assignment.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $idRaw = $request->input('id');
        $id = \is_scalar($idRaw) ? (int) $idRaw : 0;
        $assignment = ModelFinder::findOrAbort(ShiftAssignment::class, $id);
        $assignment->load('shiftRequirement');

        $req = $assignment->getShiftRequirement();
        if ($req !== null) {
            $schedule = ModelFinder::findOrAbort(Schedule::class, $req->getScheduleId());
            if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
                \abort(403);
            }
        }

        $this->assignments->unassign($assignment);

        $request->session()->flash('success', \__('Assignment removed.'));

        return \back();
    }
}
