<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Enums\ShiftSourceEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftRequirementValidity;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShiftRequirementStoreController
{
    use ValidatesWebRequests;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly AssignmentService $assignments,
    ) {}

    /**
     * Create a new shift requirement.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $scheduleId = (int) $request->query('schedule_id', '0');
        $schedule = ModelFinder::findOrAbort(Schedule::class, $scheduleId);
        $actor = User::mustAuth();

        if (!Authorization::canManageSchedule($actor, $schedule)) {
            \abort(403);
        }

        $validity = ShiftRequirementValidity::inject();
        $validated = $this->validateRequest($request, [
            'date' => $validity->date()->required()->toArray(),
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
            'role_label' => $validity->roleLabel()->nullable()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
            'employee_profile_ids' => 'nullable|array',
            'employee_profile_ids.*' => 'integer|distinct|exists:employee_profiles,id',
        ]);

        $employeeIds = [];
        foreach ($validated->assertNullableArray('employee_profile_ids') ?? [] as $employeeId) {
            if (\is_scalar($employeeId)) {
                $employeeIds[] = (int) $employeeId;
            }
        }
        $employeeIds = \array_values(\array_unique($employeeIds));

        $employees = [];
        foreach ($employeeIds as $employeeId) {
            $employee = EmployeeProfile::query()->find($employeeId);
            if (!$employee instanceof EmployeeProfile) {
                \abort(404);
            }

            Authorization::mustViewEmployee($actor, $employee);
            $employees[] = $employee;
        }

        DB::transaction(function () use ($actor, $employees, $schedule, $validated): void {
            $req = new ShiftRequirement();
            $req->forceFill([
                'schedule_id' => $schedule->getKey(),
                'store_id' => $schedule->getStoreId(),
                'date' => $validated->assertString('date'),
                'start_time' => $validated->assertString('start_time'),
                'end_time' => $validated->assertString('end_time'),
                'role_label' => $validated->assertNullableString('role_label'),
                'note' => $validated->assertNullableString('note'),
                'source' => ShiftSourceEnum::Manual->value,
                'created_by' => $actor->getKey(),
            ])->save();

            foreach ($employees as $employee) {
                $this->assignments->assignWithoutRecompute($req, $employee, $actor);
            }
        });

        $request->session()->flash('create_shift_modal_success', \__('Shift created.'));

        return \back();
    }
}
