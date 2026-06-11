<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftRequirementValidity;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftRequirementUpdateController
{
    use ValidatesWebRequests;

    /**
     * Constructor.
     */
    public function __construct(private readonly ConflictDetectionService $conflicts) {}

    /**
     * Update a shift requirement.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
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

        $validity = ShiftRequirementValidity::inject();
        $validated = $this->validateRequest($request, [
            'date' => $validity->date()->required()->toArray(),
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
            'required_employee_count' => $validity->requiredEmployeeCount()->required()->toArray(),
            'role_label' => $validity->roleLabel()->nullable()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
        ]);

        $req->forceFill([
            'date' => $validated->assertString('date'),
            'start_time' => $validated->assertString('start_time'),
            'end_time' => $validated->assertString('end_time'),
            'required_employee_count' => (int) $validated->mixed('required_employee_count'),
            'role_label' => $validated->assertNullableString('role_label'),
            'note' => $validated->assertNullableString('note'),
        ])->save();

        $this->conflicts->recompute($schedule);

        $request->session()->flash('success', \__('Shift updated.'));

        return \back();
    }
}
