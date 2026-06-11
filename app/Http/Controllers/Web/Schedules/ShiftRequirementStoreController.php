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

class ShiftRequirementStoreController
{
    use ValidatesWebRequests;

    /**
     * Constructor.
     */
    public function __construct(private readonly ConflictDetectionService $conflicts) {}

    /**
     * Create a new shift requirement.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $scheduleId = (int) $request->query('schedule_id', '0');
        $row = Schedule::query()->getQuery()->getQuery()->where('id', $scheduleId)->first();
        if ($row === null) {
            \abort(404);
        }
        $schedule = Db::hydrateOne($row, Schedule::class);
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

        $req = new ShiftRequirement();
        $req->forceFill([
            'schedule_id' => $schedule->getKey(),
            'store_id' => $schedule->getStoreId(),
            'date' => $validated->assertString('date'),
            'start_time' => $validated->assertString('start_time'),
            'end_time' => $validated->assertString('end_time'),
            'required_employee_count' => (int) $validated->mixed('required_employee_count'),
            'role_label' => $validated->assertNullableString('role_label'),
            'note' => $validated->assertNullableString('note'),
            'source' => 'manual',
            'created_by' => User::mustAuth()->getKey(),
        ])->save();

        $this->conflicts->recompute($schedule);

        $request->session()->flash('success', \__('Shift created.'));

        return \back();
    }
}
