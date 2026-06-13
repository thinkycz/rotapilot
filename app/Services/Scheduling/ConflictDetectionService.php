<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\ConflictSeverityEnum;
use App\Enums\ConflictTypeEnum;
use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use stdClass;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * Scans a schedule and detects conflicts at runtime.
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
     * Detect all conflicts for a schedule dynamically.
     *
     * @return array<int, array<string, mixed>>
     */
    public function detect(Schedule $schedule): array
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

        $conflicts = [];
        $counter = 1;

        $conflicts = \array_merge($conflicts, $this->detectOutsideBusinessHours($store, $requirements, $counter));
        $conflicts = \array_merge($conflicts, $this->detectEmployeeIssues($requirements, $availabilities, $counter));
        $conflicts = \array_merge($conflicts, $this->detectOverlaps($requirements, $counter));

        return \array_merge($conflicts, $this->detectMaxHours($schedule, $requirements, $counter));
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
     * Detect shifts outside the store's business hours.
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectOutsideBusinessHours(Store|null $store, iterable $requirements, int &$counter): array
    {
        if ($store === null) {
            return [];
        }

        $conflicts = [];
        foreach ($requirements as $r) {
            if (!$this->businessHours->isWithinBusinessHours($store, $r->getDate(), $r->getStartTime(), $r->getEndTime())) {
                $conflicts[] = $this->makeConflict(
                    $counter++,
                    $r->getKey(),
                    null,
                    ConflictTypeEnum::OutsideBusinessHours,
                    ConflictSeverityEnum::Warning,
                    Typer::assertString(\__('Shift is outside the store business hours.')),
                    Typer::assertString(\__('Adjust the shift window or update the business hours.')),
                );
            }
        }

        return $conflicts;
    }

    /**
     * Detect per-employee issues (unavailable / missing availability).
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     * @param array<int|string, \Illuminate\Support\Collection<int, stdClass>> $availabilities
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectEmployeeIssues(iterable $requirements, array $availabilities, int &$counter): array
    {
        $conflicts = [];
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

                $verdict = $this->availability->check($models, $r->getDate(), $row->getStartTime(), $row->getEndTime());

                if ($verdict === AvailabilityVerdict::Unavailable) {
                    $conflicts[] = $this->makeConflict(
                        $counter++,
                        $r->getKey(),
                        $employeeId,
                        ConflictTypeEnum::UnavailableEmployee,
                        ConflictSeverityEnum::Critical,
                        Typer::assertString(\__('Employee is marked unavailable for this time.')),
                        Typer::assertString(\__('Remove this assignment or pick another employee.')),
                    );
                } elseif ($verdict === AvailabilityVerdict::Missing) {
                    $conflicts[] = $this->makeConflict(
                        $counter++,
                        $r->getKey(),
                        $employeeId,
                        ConflictTypeEnum::MissingAvailability,
                        ConflictSeverityEnum::Info,
                        Typer::assertString(\__('No availability record for this date.')),
                        Typer::assertString(\__('Enter availability for the employee.')),
                    );
                }
            }
        }

        return $conflicts;
    }

    /**
     * Detect overlapping shifts per employee.
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectOverlaps(iterable $requirements, int &$counter): array
    {
        $conflicts = [];
        $byEmployee = [];
        foreach ($requirements as $r) {
            $rows = $r->assignments()
                ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
                ->get();
            foreach ($rows as $a) {
                $byEmployee[$a->getEmployeeProfileId()][] = [
                    'requirement' => $r,
                    'assignment' => $a,
                ];
            }
        }

        foreach ($byEmployee as $employeeId => $items) {
            foreach ($items as $item1) {
                foreach ($items as $item2) {
                    $r1 = $item1['requirement'];
                    $a1 = $item1['assignment'];
                    $r2 = $item2['requirement'];
                    $a2 = $item2['assignment'];

                    if ($a1->getKey() >= $a2->getKey()) {
                        continue;
                    }

                    if ($r1->getDate() !== $r2->getDate()) {
                        continue;
                    }

                    if ($a1->getStartTime() < $a2->getEndTime() && $a2->getStartTime() < $a1->getEndTime()) {
                        $conflicts[] = $this->makeConflict(
                            $counter++,
                            $r1->getKey(),
                            $employeeId,
                            ConflictTypeEnum::OverlappingShift,
                            ConflictSeverityEnum::Critical,
                            Typer::assertString(\__('Employee is assigned to overlapping shifts.')),
                            Typer::assertString(\__('Remove one of the overlapping assignments.')),
                        );
                        $conflicts[] = $this->makeConflict(
                            $counter++,
                            $r2->getKey(),
                            $employeeId,
                            ConflictTypeEnum::OverlappingShift,
                            ConflictSeverityEnum::Critical,
                            Typer::assertString(\__('Employee is assigned to overlapping shifts.')),
                            Typer::assertString(\__('Remove one of the overlapping assignments.')),
                        );
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Detect per-employee weekly max-hours violations.
     *
     * @param iterable<int|string, ShiftRequirement> $requirements
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectMaxHours(Schedule $schedule, iterable $requirements, int &$counter): array
    {
        $conflicts = [];
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
            return [];
        }

        $byEmployee = [];
        $profiles = EmployeeProfile::query()->whereIn('id', \array_keys($employeeIds))->get();
        foreach ($profiles as $employee) {
            $byEmployee[$employee->getKey()] = $employee;
        }

        $weeklyHours = [];

        foreach ($requirements as $r) {
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

                $duration = $this->durationHours($row->getStartTime(), $row->getEndTime());
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

                $conflicts[] = $this->makeConflict(
                    $counter++,
                    null,
                    $employeeId,
                    ConflictTypeEnum::MaxHoursExceeded,
                    ConflictSeverityEnum::Warning,
                    Typer::assertString(\__('Employee scheduled for :hours h this week (max :max h).', ['hours' => $hours, 'max' => $max])),
                    Typer::assertString(\__('Reduce assignments or raise the weekly cap.')),
                );
            }
        }

        return $conflicts;
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
     * Helper to make standardized conflict array.
     *
     * @return array<string, mixed>
     */
    private function makeConflict(
        int $id,
        int|null $requirementId,
        int|null $employeeId,
        ConflictTypeEnum $type,
        ConflictSeverityEnum $severity,
        string $message,
        string|null $suggestedFix,
    ): array {
        return [
            'id' => $id,
            'shift_requirement_id' => $requirementId,
            'employee_profile_id' => $employeeId,
            'type' => $type->value,
            'severity' => $severity->value,
            'message' => $message,
            'suggested_fix' => $suggestedFix,
        ];
    }
}
