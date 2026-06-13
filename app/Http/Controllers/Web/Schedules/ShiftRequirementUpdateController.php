<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftRequirementValidity;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftRequirementUpdateController
{
    use ValidatesWebRequests;

    /**
     * Update a shift requirement.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $req = ModelFinder::findOrAbort(ShiftRequirement::class, $id);

        $schedule = ModelFinder::findOrAbort(Schedule::class, $req->getScheduleId());

        if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
            \abort(403);
        }

        $validity = ShiftRequirementValidity::inject();
        $validated = $this->validateRequest($request, [
            'date' => $validity->date()->required()->toArray(),
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
            'role_label' => $validity->roleLabel()->nullable()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
        ]);

        $req->forceFill([
            'date' => $validated->assertString('date'),
            'start_time' => $validated->assertString('start_time'),
            'end_time' => $validated->assertString('end_time'),
            'role_label' => $validated->assertNullableString('role_label'),
            'note' => $validated->assertNullableString('note'),
        ])->save();

        $request->session()->flash('success', \__('Shift updated.'));

        return \back();
    }
}
