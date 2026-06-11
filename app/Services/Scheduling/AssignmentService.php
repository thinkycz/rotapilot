<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\User;

/**
 * Wraps the generator and the conflict service to apply assignments.
 */
class AssignmentService
{
    /**
     * Schedule generator.
     */
    private readonly ScheduleGeneratorService $generator;

    /**
     * Conflict detection service.
     */
    private readonly ConflictDetectionService $conflicts;

    /**
     * Constructor.
     */
    public function __construct(
        ScheduleGeneratorService $generator,
        ConflictDetectionService $conflicts,
    ) {
        $this->generator = $generator;
        $this->conflicts = $conflicts;
    }

    /**
     * Auto-fill a shift with the best candidates.
     *
     * @return array<int, ShiftAssignment>
     */
    public function autoFill(ShiftRequirement $requirement, User $actor): array
    {
        $picks = $this->generator->proposeAssignments($requirement);

        $created = [];
        foreach ($picks as $employeeId) {
            $employee = $this->findEmployee($employeeId);
            if ($employee === null) {
                continue;
            }

            $existing = $this->findAssignment($requirement->getKey(), $employee->getKey());
            if ($existing instanceof ShiftAssignment) {
                $created[] = $existing;

                continue;
            }

            $assignment = new ShiftAssignment();
            $assignment->forceFill([
                'shift_requirement_id' => $requirement->getKey(),
                'employee_profile_id' => $employee->getKey(),
                'status' => ShiftAssignmentStatusEnum::Draft->value,
                'source' => 'manual',
                'assigned_by' => $actor->getKey(),
            ])->save();

            $created[] = $assignment;
        }

        $schedule = $requirement->schedule;
        if ($schedule instanceof Schedule) {
            $this->conflicts->recompute($schedule);
        }

        return $created;
    }

    /**
     * Manually assign a single employee.
     */
    public function assign(ShiftRequirement $requirement, EmployeeProfile $employee, User $actor): ShiftAssignment
    {
        $existing = $this->findAssignment($requirement->getKey(), $employee->getKey());
        if ($existing instanceof ShiftAssignment) {
            return $existing;
        }

        $assignment = new ShiftAssignment();
        $assignment->forceFill([
            'shift_requirement_id' => $requirement->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'status' => ShiftAssignmentStatusEnum::Draft->value,
            'source' => 'manual',
            'assigned_by' => $actor->getKey(),
        ])->save();

        $schedule = $requirement->schedule;
        if ($schedule instanceof Schedule) {
            $this->conflicts->recompute($schedule);
        }

        return $assignment;
    }

    /**
     * Remove an assignment.
     */
    public function unassign(ShiftAssignment $assignment): void
    {
        $assignment->delete();
        $schedule = $assignment->shiftRequirement?->schedule;
        if ($schedule instanceof Schedule) {
            $this->conflicts->recompute($schedule);
        }
    }

    /**
     * Find an employee profile by id.
     */
    private function findEmployee(int $id): EmployeeProfile|null
    {
        return EmployeeProfile::query()->find($id);
    }

    /**
     * Find an existing assignment for a requirement and employee.
     */
    private function findAssignment(int $requirementId, int $employeeId): ShiftAssignment|null
    {
        $rows = ShiftAssignment::query()
            ->getQuery()
            ->where('shift_requirement_id', $requirementId)
            ->where('employee_profile_id', $employeeId)
            ->get();

        $row = $rows->first();
        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $attrs */
        $attrs = (array) $row;

        return (new ShiftAssignment())->newFromBuilder($attrs);
    }
}
