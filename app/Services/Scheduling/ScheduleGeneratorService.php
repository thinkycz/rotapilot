<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Support\Db;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use stdClass;

/**
 * Deterministic scheduler. Picks up to N employees for a shift requirement.
 */
class ScheduleGeneratorService
{
    /**
     * Availability matcher.
     */
    private readonly AvailabilityMatcherService $availability;

    /**
     * Constructor.
     */
    public function __construct(AvailabilityMatcherService $availability)
    {
        $this->availability = $availability;
    }

    /**
     * Propose employee assignments for a shift requirement.
     *
     * @return array<int, int> employee_profile_ids
     */
    public function proposeAssignments(ShiftRequirement $requirement): array
    {
        $store = $requirement->store;

        if (!$store instanceof \App\Models\Store) {
            return [];
        }

        $storeId = $store->getKey();
        $date = $requirement->getDate();
        $start = $requirement->getStartTime();
        $end = $requirement->getEndTime();

        $candidateIds = $this->candidateIdsForStore($storeId);
        if (\count($candidateIds) === 0) {
            return [];
        }

        /** @var \Illuminate\Support\Collection<int, stdClass> $rawEmployees */
        $rawEmployees = EmployeeProfile::query()
            ->getQuery()
            ->where('id', $candidateIds)
            ->where('is_active', true)
            ->get();

        /** @var EloquentCollection<int, EmployeeProfile> $employees */
        $employees = Db::hydrate($rawEmployees, EmployeeProfile::class);

        if ($employees->isEmpty()) {
            return [];
        }

        $employeeIds = [];
        foreach ($employees as $e) {
            $employeeIds[] = $e->getKey();
        }

        $availabilityRows = EmployeeAvailability::query()
            ->getQuery()
            ->whereIn('employee_profile_id', $employeeIds)
            ->where('date', $date)
            ->get();

        /** @var \Illuminate\Support\Collection<int, stdClass> $availabilityRows */
        $availabilities = $availabilityRows->groupBy('employee_profile_id');

        $weeklyHours = $this->weeklyHoursForEmployees($employeeIds, $requirement);

        $ranked = [];
        foreach ($employees as $employee) {
            $employeeId = $employee->getKey();
            $bucket = $availabilities[$employeeId] ?? null;
            $models = [];
            if ($bucket !== null) {
                foreach ($bucket as $item) {
                    $model = new EmployeeAvailability();
                    $model->setRawAttributes((array) $item, true);
                    $models[] = $model;
                }
            }

            $verdict = $this->availability->check($models, $date, $start, $end);

            if ($verdict !== AvailabilityVerdict::Available) {
                continue;
            }

            if ($this->hasOverlap($employeeId, $requirement)) {
                continue;
            }

            $max = $employee->getMaxHoursPerWeek();
            $ranked[] = [
                'id' => $employeeId,
                'max' => $max,
                'weekly' => $weeklyHours[$employeeId] ?? 0.0,
            ];
        }

        \usort($ranked, static function (array $a, array $b): int {
            $capA = $a['max'] ?? \PHP_INT_MAX;
            $capB = $b['max'] ?? \PHP_INT_MAX;

            if ($capA !== $capB) {
                return $capA <=> $capB;
            }

            return $a['weekly'] <=> $b['weekly'];
        });

        $need = $requirement->getRequiredEmployeeCount() - $requirement->getAssignedCount();
        if ($need <= 0) {
            return [];
        }

        $picks = [];
        foreach ($ranked as $row) {
            if ($need <= \count($picks)) {
                break;
            }

            $picks[] = (int) $row['id'];
        }

        return $picks;
    }

    /**
     * Get the active employee profile ids assigned to the given store.
     *
     * @return array<int, int>
     */
    private function candidateIdsForStore(int $storeId): array
    {
        $rows = \Illuminate\Database\Eloquent\Model::query()
            ->getQuery()
            ->from('employee_store')
            ->where('store_id', $storeId)
            ->get(['employee_profile_id']);

        $ids = [];
        foreach ($rows as $r) {
            $raw = $r->employee_profile_id;
            if (\is_int($raw)) {
                $ids[] = $raw;
            } elseif (\is_string($raw) && \ctype_digit($raw)) {
                $ids[] = (int) $raw;
            }
        }

        return $ids;
    }

    /**
     * Compute the weekly hours scheduled per employee for the week containing the requirement.
     *
     * @param array<int, int> $candidateIds
     *
     * @return array<int, float>
     */
    private function weeklyHoursForEmployees(array $candidateIds, ShiftRequirement $requirement): array
    {
        $weekStart = \Carbon\Carbon::parse($requirement->getDate())->startOfWeek()->format('Y-m-d');
        $weekEnd = \Carbon\Carbon::parse($requirement->getDate())->endOfWeek()->format('Y-m-d');

        /** @var \Illuminate\Support\Collection<int, stdClass> $rawAssignments */
        $rawAssignments = ShiftAssignment::query()
            ->getQuery()
            ->whereIn('employee_profile_id', $candidateIds)
            ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
            ->get();

        /** @var EloquentCollection<int, ShiftAssignment> $assignments */
        $assignments = Db::hydrate($rawAssignments, ShiftAssignment::class);

        $requirementIds = [];
        foreach ($assignments as $a) {
            $requirementIds[] = $a->getShiftRequirementId();
        }

        if (\count($requirementIds) === 0) {
            return [];
        }

        /** @var \Illuminate\Support\Collection<int, stdClass> $rawRequirements */
        $rawRequirements = ShiftRequirement::query()
            ->getQuery()
            ->whereIn('id', $requirementIds)
            ->get();

        /** @var EloquentCollection<int, ShiftRequirement> $requirements */
        $requirements = Db::hydrate($rawRequirements, ShiftRequirement::class);

        $byId = [];
        foreach ($requirements as $r) {
            $byId[$r->getKey()] = $r;
        }

        $hours = [];
        foreach ($assignments as $assignment) {
            $req = $byId[$assignment->getShiftRequirementId()] ?? null;
            if ($req === null) {
                continue;
            }
            if ($weekStart > $req->getDate() || $weekEnd < $req->getDate()) {
                continue;
            }
            $duration = $this->durationHours($req->getStartTime(), $req->getEndTime());
            $employeeId = $assignment->getEmployeeProfileId();
            $hours[$employeeId] = ($hours[$employeeId] ?? 0.0) + $duration;
        }

        return $hours;
    }

    /**
     * Check whether the employee has any overlapping assignment that would conflict with the requirement.
     */
    private function hasOverlap(int $employeeId, ShiftRequirement $r): bool
    {
        /** @var \Illuminate\Support\Collection<int, stdClass> $rawOverlapping */
        $rawOverlapping = ShiftAssignment::query()
            ->getQuery()
            ->where('employee_profile_id', $employeeId)
            ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value)
            ->get();

        /** @var EloquentCollection<int, ShiftAssignment> $overlapping */
        $overlapping = Db::hydrate($rawOverlapping, ShiftAssignment::class);

        $requirementIds = [];
        foreach ($overlapping as $a) {
            $requirementIds[] = $a->getShiftRequirementId();
        }

        if (\count($requirementIds) === 0) {
            return false;
        }

        /** @var \Illuminate\Support\Collection<int, stdClass> $rawOthers */
        $rawOthers = ShiftRequirement::query()
            ->getQuery()
            ->whereIn('id', $requirementIds)
            ->get();

        /** @var EloquentCollection<int, ShiftRequirement> $others */
        $others = Db::hydrate($rawOthers, ShiftRequirement::class);

        foreach ($others as $other) {
            if ($other->getDate() !== $r->getDate()) {
                continue;
            }

            if ($r->getStartTime() < $other->getEndTime() && $other->getStartTime() < $r->getEndTime()) {
                return true;
            }
        }

        return false;
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
    private function parseTime(string $value): \Carbon\Carbon
    {
        $parsed = \Carbon\Carbon::createFromFormat('H:i', $value);

        if ($parsed instanceof \Carbon\Carbon) {
            return $parsed;
        }

        $parsed = \Carbon\Carbon::createFromFormat('H:i:s', $value);

        if ($parsed instanceof \Carbon\Carbon) {
            return $parsed;
        }

        return \Carbon\Carbon::createFromTime(
            (int) \mb_substr($value, 0, 2),
            (int) \mb_substr($value, 3, 2),
        );
    }
}
