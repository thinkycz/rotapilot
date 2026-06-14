<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Employees;

use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\Store;
use App\Models\User;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeShowController
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly ConflictDetectionService $conflictDetector,
    ) {}

    /**
     * Show a single employee.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $employee = ModelFinder::findOrAbort(EmployeeProfile::class, $id);

        Authorization::mustViewEmployee($user, $employee);

        $employee->loadMissing('user');
        $login = $employee->getUser();
        $storeList = $employee->stores()->orderBy('name')->get();
        $now = CarbonImmutable::now();
        $today = $now->format('Y-m-d');
        $endOfWeek = $now->endOfWeek()->format('Y-m-d');
        $endOfMonth = $now->endOfMonth()->format('Y-m-d');
        $nextSeven = $now->addDays(6)->format('Y-m-d');

        $assignmentsQ = ShiftAssignment::query()
            ->with(['shiftRequirement' => static function (Relation $query): void {
                $query->with('store');
            }])
            ->where('employee_profile_id', $employee->getKey())
            ->whereHas(
                'shiftRequirement',
                static fn($q) => $q->where('date', '>=', $today),
            )
            ->orderBy('start_time')
            ->get();

        $upcomingShifts = $assignmentsQ
            ->take(5)
            ->map(static function (ShiftAssignment $a): array {
                $req = $a->getShiftRequirement();
                $store = $req->getStore();

                return [
                    'id' => $a->getKey(),
                    'date' => $req->getDate(),
                    'start_time' => $a->getStartTime(),
                    'end_time' => $a->getEndTime(),
                    'role_label' => $req->getRoleLabel(),
                    'status' => $a->getStatus()->value,
                    'schedule_id' => $req->getScheduleId(),
                    'store_id' => $req->getStoreId(),
                    'store_name' => $store->getName(),
                ];
            })
            ->values()
            ->all();

        $hoursThisWeek = $this->sumAssignmentHours(
            $assignmentsQ,
            static fn(string $date): bool => $date >= $today && $date <= $endOfWeek,
        );
        $hoursThisMonth = $this->sumAssignmentHours(
            $assignmentsQ,
            static fn(string $date): bool => $date >= $today && $date <= $endOfMonth,
        );
        $hoursTotal = $this->sumAssignmentHours(
            $assignmentsQ,
            static fn(string $date): bool => $date >= $today,
        );

        $conflictCount = $this->countEmployeeConflicts($employee, $today);

        $availabilitySummary = $this->buildAvailabilitySummary(
            $employee,
            $today,
            $nextSeven,
        );

        $loginRaw = $login instanceof User
            ? [
                'id' => $login->getKey(),
                'email' => $login->getEmail(),
                'locale' => $login->getLocale(),
            ]
            : null;

        return Inertia::render('employees/Show', [
            'employee' => [
                'id' => $employee->getKey(),
                'name' => $employee->getName(),
                'email' => $employee->getEmail(),
                'phone' => $employee->getPhone(),
                'role_label' => $employee->getRoleLabel(),
                'max_hours_per_week' => $employee->getMaxHoursPerWeek(),
                'hourly_rate' => $employee->getHourlyRate(),
                'is_active' => $employee->getIsActive(),
                'has_login' => $employee->hasLoginAccount(),
                'login' => $loginRaw,
                'public_schedule_url' => '/public/employee-schedules?token=' . $employee->ensurePublicScheduleToken(),
            ],
            'stores' => $storeList->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
            'stats' => [
                'upcoming_shifts' => \count($upcomingShifts),
                'hours_this_week' => $hoursThisWeek,
                'hours_this_month' => $hoursThisMonth,
                'hours_total' => $hoursTotal,
                'conflicts' => $conflictCount,
            ],
            'upcoming_shifts' => $upcomingShifts,
            'availability' => $availabilitySummary,
        ]);
    }

    /**
     * Sum the hours of shift assignments filtered by their shift requirement's date.
     *
     * @param \Illuminate\Support\Collection<int, ShiftAssignment> $assignments
     * @param callable(string $date): bool $dateFilter
     */
    private function sumAssignmentHours(iterable $assignments, callable $dateFilter): float
    {
        $total = 0.0;

        foreach ($assignments as $a) {
            if ($a->getStatus() === ShiftAssignmentStatusEnum::Cancelled) {
                continue;
            }
            $req = $a->getShiftRequirement();
            if (!$dateFilter($req->getDate())) {
                continue;
            }
            $total += $this->hoursBetween($a->getStartTime(), $a->getEndTime());
        }

        return \round($total, 2);
    }

    /**
     * Count conflicts that touch this employee across their active schedules.
     */
    private function countEmployeeConflicts(EmployeeProfile $employee, string $today): int
    {
        $scheduleIds = Schedule::query()
            ->where('period_end', '>=', $today)
            ->whereHas('shiftRequirements.assignments', static function ($q) use ($employee): void {
                $q->where('employee_profile_id', $employee->getKey());
            })
            ->pluck('id')
            ->all();

        if ($scheduleIds === []) {
            return 0;
        }

        $count = 0;
        foreach ($scheduleIds as $scheduleId) {
            $schedule = Schedule::query()
                ->with('shiftRequirements')
                ->find($scheduleId);
            if (!$schedule instanceof Schedule) {
                continue;
            }
            foreach ($this->conflictDetector->detect($schedule) as $c) {
                if ($c['employee_profile_id'] === $employee->getKey()) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * Build a 7-day availability summary starting at $today.
     *
     * @return array<int, array{date: string, weekday: string, has_unavailable_entry: bool}>
     */
    private function buildAvailabilitySummary(EmployeeProfile $employee, string $today, string $nextSeven): array
    {
        $entries = EmployeeAvailability::query()
            ->where('employee_profile_id', $employee->getKey())
            ->whereBetween('date', [$today, $nextSeven])
            ->get();

        $unavailableByDate = [];
        foreach ($entries as $entry) {
            if ($entry->getType()->value === 'unavailable') {
                $unavailableByDate[$entry->getDate()] = true;
            }
        }

        $start = CarbonImmutable::parse($today);
        $weekdayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $summary = [];
        for ($i = 0; $i < 7; ++$i) {
            $d = $start->addDays($i);
            $date = $d->format('Y-m-d');
            $summary[] = [
                'date' => $date,
                'weekday' => $weekdayKeys[$d->dayOfWeekIso - 1] ?? 'sun',
                'has_unavailable_entry' => isset($unavailableByDate[$date]),
            ];
        }

        return $summary;
    }

    /**
     * Hours between two HH:MM:SS strings, with rollover past midnight supported.
     */
    private function hoursBetween(string $start, string $end): float
    {
        $startDt = CarbonImmutable::createFromFormat('H:i:s', $start);
        if (!$startDt instanceof CarbonImmutable) {
            $startDt = CarbonImmutable::createFromFormat('H:i', $start);
        }
        $endDt = CarbonImmutable::createFromFormat('H:i:s', $end);
        if (!$endDt instanceof CarbonImmutable) {
            $endDt = CarbonImmutable::createFromFormat('H:i', $end);
        }
        if (!$startDt instanceof CarbonImmutable || !$endDt instanceof CarbonImmutable) {
            return 0.0;
        }
        if ($endDt->lessThan($startDt)) {
            $endDt = $endDt->addDay();
        }

        return ($endDt->getTimestamp() - $startDt->getTimestamp()) / 3600.0;
    }
}
