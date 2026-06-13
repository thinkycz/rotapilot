<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleShowController
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly ConflictDetectionService $conflictDetector,
    ) {}

    /**
     * Show a schedule.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $schedule = ModelFinder::findOrAbort(Schedule::class, $id);

        if (!Authorization::canViewSchedule($user, $schedule)) {
            \abort(403);
        }

        $schedule->loadMissing(['store', 'shiftRequirements']);
        $store = $schedule->getStore();
        $requirements = $schedule->getShiftRequirements();
        $conflicts = $this->conflictDetector->detect($schedule);

        $start = Carbon::parse($schedule->getPeriodStart());
        $end = Carbon::parse($schedule->getPeriodEnd());

        $byDate = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $byDate[$d->format('Y-m-d')] = [
                'shifts' => [],
            ];
        }

        foreach ($requirements as $r) {
            $date = $r->getDate();
            if (isset($byDate[$date])) {
                $byDate[$date]['shifts'][] = [
                    'id' => $r->getKey(),
                    'start_time' => $r->getStartTime(),
                    'end_time' => $r->getEndTime(),
                    'role_label' => $r->getRoleLabel(),
                    'note' => $r->getNote(),
                    'source' => $r->getSource()->value,
                    'assignments' => $r->assignments()
                        ->with('employeeProfile')
                        ->orderBy('start_time')
                        ->get()
                        ->map(static fn(ShiftAssignment $a): array => [
                            'id' => $a->getKey(),
                            'employee_profile_id' => $a->getEmployeeProfileId(),
                            'employee_name' => $a->getEmployeeProfile()->getName(),
                            'start_time' => $a->getStartTime(),
                            'end_time' => $a->getEndTime(),
                            'status' => $a->getStatus()->value,
                        ])->values()->all(),
                ];
            }
        }
        \ksort($byDate);

        $employees = $store->employees()->orderBy('name')->get();

        return Inertia::render('schedules/Show', [
            'schedule' => [
                'id' => $schedule->getKey(),
                'name' => $schedule->getName(),
                'status' => $schedule->getStatus()->value,
                'period_start' => $schedule->getPeriodStart(),
                'period_end' => $schedule->getPeriodEnd(),
                'store_id' => $schedule->getStoreId(),
                'store_name' => $store->getName(),
            ],
            'days' => $byDate,
            'conflicts' => \collect($conflicts)->map(static fn(array $c): array => [
                'id' => $c['id'],
                'type' => $c['type'],
                'severity' => $c['severity'],
                'message' => $c['message'],
                'suggested_fix' => $c['suggested_fix'],
                'employee_id' => $c['employee_profile_id'],
                'shift_requirement_id' => $c['shift_requirement_id'],
            ])->values()->all(),
            'employees' => $employees->map(static fn(EmployeeProfile $e): array => [
                'id' => $e->getKey(),
                'name' => $e->getName(),
            ])->values()->all(),
        ]);
    }
}
