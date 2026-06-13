<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Deterministic scheduler. Picks one eligible employee for an open shift requirement.
 */
class ScheduleGeneratorService
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly AvailabilityMatcherService $availability,
    ) {}

    /**
     * Propose employee assignments for a shift requirement.
     *
     * @return array<int, int> employee_profile_ids
     */
    public function proposeAssignments(ShiftRequirement $requirement): array
    {
        $storeId = $requirement->getStoreId();
        $date = $requirement->getDate();
        $start = $requirement->getStartTime();
        $end = $requirement->getEndTime();

        $candidateIds = $this->candidateIdsForStore($storeId);
        if (\count($candidateIds) === 0) {
            return [];
        }

        $employees = EmployeeProfile::query()
            ->whereIn('id', $candidateIds)
            ->tap(static fn($q) => EmployeeProfile::scopeActive($q))
            ->get();

        if ($employees->isEmpty()) {
            return [];
        }

        $employeeIds = [];
        foreach ($employees as $e) {
            $employeeIds[] = $e->getKey();
        }

        $availabilities = EmployeeAvailability::query()
            ->whereIn('employee_profile_id', $employeeIds)
            ->where('date', $date)
            ->get()
            ->groupBy('employee_profile_id');

        $weeklyHours = $this->weeklyHoursForEmployees($employeeIds, $requirement);

        $ranked = [];
        foreach ($employees as $employee) {
            $employeeId = $employee->getKey();
            $bucket = $availabilities->get($employeeId);
            /** @var array<int, EmployeeAvailability> $models */
            $models = $bucket instanceof EloquentCollection ? $bucket->all() : [];

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

        $need = 1 - $requirement->getAssignedCount();
        if ($need <= 0) {
            return [];
        }

        $picks = [];
        foreach ($ranked as $row) {
            if ($need <= \count($picks)) {
                break;
            }

            $picks[] = $row['id'];
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
        $ids = [];
        $profileIds = DB::table('employee_store')
            ->where('store_id', $storeId)
            ->pluck('employee_profile_id')
            ->all();

        foreach ($profileIds as $id) {
            if (\is_int($id)) {
                $ids[] = $id;
            } elseif (\is_string($id) && \ctype_digit($id)) {
                $ids[] = (int) $id;
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

        $assignments = ShiftAssignment::query()
            ->tap(static fn($q) => ShiftAssignment::scopeActive($q))
            ->whereIn('employee_profile_id', $candidateIds)
            ->get();

        $requirementIds = [];
        foreach ($assignments as $a) {
            $requirementIds[] = $a->getShiftRequirementId();
        }

        if (\count($requirementIds) === 0) {
            return [];
        }

        $requirements = ShiftRequirement::query()
            ->whereIn('id', $requirementIds)
            ->get();

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
            $duration = $this->durationHours($assignment->getStartTime(), $assignment->getEndTime());
            $employeeId = $assignment->getEmployeeProfileId();
            $hours[$employeeId] = ($hours[$employeeId] ?? 0.0) + $duration;
        }

        return $hours;
    }

    /**
     * Check whether the employee has any overlapping assignment that would
     * conflict with the requirement.
     */
    private function hasOverlap(int $employeeId, ShiftRequirement $r): bool
    {
        $overlapping = ShiftAssignment::query()
            ->tap(static fn($q) => ShiftAssignment::scopeActive($q))
            ->where('employee_profile_id', $employeeId)
            ->with('shiftRequirement')
            ->get();

        foreach ($overlapping as $a) {
            $req = $a->getShiftRequirement();
            if ($req->getKey() === $r->getKey()) {
                continue;
            }
            if ($req->getDate() !== $r->getDate()) {
                continue;
            }

            if ($r->getStartTime() < $a->getEndTime() && $r->getEndTime() > $a->getStartTime()) {
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
        try {
            $parsed = \Carbon\Carbon::createFromFormat('H:i:s', $value);
            if ($parsed instanceof \Carbon\Carbon) {
                return $parsed;
            }
        } catch (Throwable) {
        }

        try {
            $parsed = \Carbon\Carbon::createFromFormat('H:i', $value);
            if ($parsed instanceof \Carbon\Carbon) {
                return $parsed;
            }
        } catch (Throwable) {
        }

        return \Carbon\Carbon::createFromTime(
            (int) \mb_substr($value, 0, 2),
            (int) \mb_substr($value, 3, 2),
        );
    }
}
