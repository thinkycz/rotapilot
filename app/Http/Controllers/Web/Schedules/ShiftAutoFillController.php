<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftAutoFillController
{
    use ValidatesWebRequests;

    /**
     * Constructor.
     */
    public function __construct(private readonly AssignmentService $assignments) {}

    /**
     * Auto-fill a shift with the best candidates.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $idRaw = $request->input('shift_requirement_id');
        $id = \is_scalar($idRaw) ? (int) $idRaw : 0;
        $req = ModelFinder::findOrAbort(ShiftRequirement::class, $id);

        $schedule = ModelFinder::findOrAbort(Schedule::class, $req->getScheduleId());

        if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
            \abort(403);
        }

        $created = $this->assignments->autoFill($req, User::mustAuth());

        if (\count($created) === 0) {
            $request->session()->flash('shift_modal_error', \__('No eligible employees found for auto-fill.'));
        } else {
            $request->session()->flash('shift_modal_success', \__('Shift auto-filled.'));
        }

        return \back();
    }
}
