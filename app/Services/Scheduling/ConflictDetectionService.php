<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\ConflictSeverityEnum;
use App\Enums\ConflictTypeEnum;
use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ScheduleConflict;
use App\Models\ShiftRequirement;
use App\Models\Store;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use stdClass;

/**
 * Scans a schedule and rewrites all conflict rows for it.
 */
class ConflictDetectionService
{
    /**
     * Availability matcher.
     */
    private readonly AvailabilityMatcherService $availability;

    /**
     * Business hour guard.
     */
    private readonly BusinessHourGuardService $businessHours;

    /**
     * Constructor.
     */
    public function __construct(
        AvailabilityMatcherService $availability,
        BusinessHourGuardService $businessHours,
    ) {
        $this->availability = $availability;
        $this->businessHours = $businessHours;
    }

    /**
     * Recompute all conflicts for a schedule.
     */
    public function recompute(Schedule $schedule): void
    {
        $schedule->loadMissing(['store', 'shiftRequirements']);
        $store = $schedule->getStore();
        $requirements = $schedule->getShiftRequirements();
        $employeeIds = $this->collectEmployeeIds($requirements);

        $availabilities = EmployeeAvailability::query()
            ->getQuery()
            ->whereIn('employee_profile_id', $employeeIds)
            ->get()
            ->groupBy('employee_profile_id')
            ->all();

        ScheduleConflict::query()->getQuery()->where('schedule_id', $schedule->getKey())->delete();

        $this->detectUnderstaffed($schedule, $requirements);
        $this->detectOutsideBusinessHours($schedule, $store, $requirements);
        $this->detectEmployeeIssues($schedule, $requirements, $availabilities);
        $this->detectOverlaps($schedule, $requirements);
        $this->detectMaxHours($schedule, $requirements);
    }

    /**
     * Collect the unique employee ids assigned to the given requirements.
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     *
     * @return array<int, int>
     */
    private function collectEmployeeIds(iterable $requirements): array
    {
        $ids = [];
        foreach ($requirements as $r) {
            $rows = $r->assignments()
                ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
                ->get();
            foreach ($rows as $a) {
                $ids[$a->getEmployeeProfileId()] = true;
            }
        }

        return \array_keys($ids);
    }

    /**
     * Detect understaffed shifts.
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     */
    private function detectUnderstaffed(Schedule $schedule, iterable $requirements): void
    {
        foreach ($requirements as $r) {
            $assigned = $r->getAssignedCount();
            if ($assigned < $r->getRequiredEmployeeCount()) {
                $missing = $r->getRequiredEmployeeCount() - $assigned;
                $this->conflict(
                    $schedule,
                    $r->getKey(),
                    null,
                    ConflictTypeEnum::Understaffed,
                    ConflictSeverityEnum::Warning,
                    "Needs {$missing} more employee(s).",
                    'Assign more employees to this shift.',
                );
            }
        }
    }

    /**
     * Detect shifts outside the store's business hours.
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     */
    private function detectOutsideBusinessHours(Schedule $schedule, Store|null $store, iterable $requirements): void
    {
        if ($store === null) {
            return;
        }

        foreach ($requirements as $r) {
            if (!$this->businessHours->isWithinBusinessHours($store, $r->getDate(), $r->getStartTime(), $r->getEndTime())) {
                $this->conflict(
                    $schedule,
                    $r->getKey(),
                    null,
                    ConflictTypeEnum::OutsideBusinessHours,
                    ConflictSeverityEnum::Warning,
                    'Shift is outside the store business hours.',
                    'Adjust the shift window or update the business hours.',
                );
            }
        }
    }

    /**
     * Detect per-employee issues (unavailable / missing availability).
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     * @param array<int|string, \Illuminate\Support\Collection<int, stdClass>> $availabilities
     */
    private function detectEmployeeIssues(Schedule $schedule, iterable $requirements, array $availabilities): void
    {
        foreach ($requirements as $r) {
            $rows = $r->assignments()
                ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
                ->get();

            foreach ($rows as $row) {
                $employeeId = $row->getEmployeeProfileId();
                $bucket = $availabilities[$employeeId] ?? null;
                $models = [];
                if ($bucket instanceof \Illuminate\Support\Collection) {
                    foreach ($bucket as $item) {
                        $model = new EmployeeAvailability();
                        $model->setRawAttributes((array) $item, true);
                        $models[] = $model;
                    }
                }

                $verdict = $this->availability->check($models, $r->getDate(), $r->getStartTime(), $r->getEndTime());

                if ($verdict === AvailabilityVerdict::Unavailable) {
                    $this->conflict(
                        $schedule,
                        $r->getKey(),
                        $employeeId,
                        ConflictTypeEnum::UnavailableEmployee,
                        ConflictSeverityEnum::Critical,
                        'Employee is marked unavailable for this time.',
                        'Remove this assignment or pick another employee.',
                    );
                } elseif ($verdict === AvailabilityVerdict::Missing) {
                    $this->conflict(
                        $schedule,
                        $r->getKey(),
                        $employeeId,
                        ConflictTypeEnum::MissingAvailability,
                        ConflictSeverityEnum::Info,
                        'No availability record for this date.',
                        'Enter availability for the employee.',
                    );
                }
            }
        }
    }

    /**
     * Detect overlapping shifts per employee.
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     */
    private function detectOverlaps(Schedule $schedule, iterable $requirements): void
    {
        $byEmployee = [];
        foreach ($requirements as $r) {
            $rows = $r->assignments()
                ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
                ->get();
            foreach ($rows as $a) {
                $byEmployee[$a->getEmployeeProfileId()][] = $r;
            }
        }

        foreach ($byEmployee as $employeeId => $reqs) {
            foreach ($reqs as $r1) {
                foreach ($reqs as $r2) {
                    if ($r1->getKey() >= $r2->getKey()) {
                        continue;
                    }

                    if ($r1->getDate() !== $r2->getDate()) {
                        continue;
                    }

                    if ($r1->getStartTime() < $r2->getEndTime() && $r2->getStartTime() < $r1->getEndTime()) {
                        $this->conflict(
                            $schedule,
                            $r1->getKey(),
                            $employeeId,
                            ConflictTypeEnum::OverlappingShift,
                            ConflictSeverityEnum::Critical,
                            'Employee is assigned to overlapping shifts.',
                            'Remove one of the overlapping assignments.',
                        );
                        $this->conflict(
                            $schedule,
                            $r2->getKey(),
                            $employeeId,
                            ConflictTypeEnum::OverlappingShift,
                            ConflictSeverityEnum::Critical,
                            'Employee is assigned to overlapping shifts.',
                            'Remove one of the overlapping assignments.',
                        );
                    }
                }
            }
        }
    }

    /**
     * Detect per-employee weekly max-hours violations.
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     */
    private function detectMaxHours(Schedule $schedule, iterable $requirements): void
    {
        $employeeIds = [];
        foreach ($requirements as $r) {
            $rows = $r->assignments()
                ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
                ->get();
            foreach ($rows as $a) {
                $employeeIds[$a->getEmployeeProfileId()] = true;
            }
        }

        if (\count($employeeIds) === 0) {
            return;
        }

        $byEmployee = [];
        $profiles = EmployeeProfile::query()->whereIn('id', \array_keys($employeeIds))->get();
        foreach ($profiles as $employee) {
            $byEmployee[$employee->getKey()] = $employee;
        }

        $weeklyHours = [];

        foreach ($requirements as $r) {
            $duration = $this->durationHours($r->getStartTime(), $r->getEndTime());
            $rows = $r->assignments()
                ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
                ->get();
            foreach ($rows as $row) {
                $employeeId = $row->getEmployeeProfileId();
                $employee = $byEmployee[$employeeId] ?? null;
                if ($employee === null) {
                    continue;
                }

                $max = $employee->getMaxHoursPerWeek();
                if ($max === null) {
                    continue;
                }

                $weekKey = (string) Carbon::parse($r->getDate())->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d');
                $weeklyHours[$employeeId][$weekKey] = ($weeklyHours[$employeeId][$weekKey] ?? 0) + $duration;
            }
        }

        foreach ($weeklyHours as $employeeId => $weeks) {
            foreach ($weeks as $hours) {
                $employee = $byEmployee[$employeeId] ?? null;
                if ($employee === null) {
                    continue;
                }
                $max = $employee->getMaxHoursPerWeek();
                if ($max === null || $hours <= $max) {
                    continue;
                }

                $this->conflict(
                    $schedule,
                    null,
                    $employeeId,
                    ConflictTypeEnum::MaxHoursExceeded,
                    ConflictSeverityEnum::Warning,
                    "Employee scheduled for {$hours}h this week (max {$max}h).",
                    'Reduce assignments or raise the weekly cap.',
                );
            }
        }
    }

    /**
     * Compute the duration in hours between two HH:MM times.
     */
    private function durationHours(string $start, string $end): float
    {
        $s = $this->parseTime($start);
        $e = $this->parseTime($end);

        if ($e->lessThan($s)) {
            $e->addDay();
        }

        return ($e->getTimestamp() - $s->getTimestamp()) / 3600.0;
    }

    /**
     * Parse a HH:MM time into a Carbon instance.
     */
    private function parseTime(string $value): Carbon
    {
        if (\preg_match('/^(?<hour>\\d{1,2}):(?<minute>\\d{2})(?::(?<second>\\d{2}))?$/', $value, $matches) === 1) {
            return Carbon::createFromTime(
                (int) $matches['hour'],
                (int) $matches['minute'],
                isset($matches['second']) ? (int) $matches['second'] : 0,
            );
        }

        return Carbon::parse($value);
    }

    /**
     * Persist a single conflict row.
     */
    private function conflict(
        Schedule $schedule,
        int|null $requirementId,
        int|null $employeeId,
        ConflictTypeEnum $type,
        ConflictSeverityEnum $severity,
        string $message,
        string|null $suggestedFix,
    ): void {
        ScheduleConflict::query()->getQuery()->insert([
            'schedule_id' => $schedule->getKey(),
            'shift_requirement_id' => $requirementId,
            'employee_profile_id' => $employeeId,
            'type' => $type->value,
            'severity' => $severity->value,
            'message' => $message,
            'suggested_fix' => $suggestedFix,
            'created_at' => \now(),
            'updated_at' => \now(),
        ]);
    }
}
