<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftRequirementDestroyController
{
    use ValidatesWebRequests;

    /**
     * Constructor.
     */
    public function __construct(private readonly ConflictDetectionService $conflicts) {}

    /**
     * Delete a shift requirement.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $idRaw = $request->input('id');
        $id = \is_scalar($idRaw) ? (int) $idRaw : 0;
        $req = ModelFinder::findOrAbort(ShiftRequirement::class, $id);

        $schedule = ModelFinder::findOrAbort(Schedule::class, $req->getScheduleId());

        if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
            \abort(403);
        }

        $req->delete();
        $this->conflicts->recompute($schedule);

        $request->session()->flash('success', \__('Shift removed.'));

        return \back();
    }
}
