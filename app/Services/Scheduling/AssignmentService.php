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

            $created[] = $this->createDraftAssignment($requirement, $employee, $actor, 'manual');
        }

        $this->recomputeForRequirement($requirement);

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

        $assignment = $this->createDraftAssignment($requirement, $employee, $actor, 'manual');

        $this->recomputeForRequirement($requirement);

        return $assignment;
    }

    /**
     * Remove an assignment.
     */
    public function unassign(ShiftAssignment $assignment): void
    {
        $requirement = $assignment->shiftRequirement;
        $assignment->delete();
        if ($requirement instanceof ShiftRequirement) {
            $this->recomputeForRequirement($requirement);
        }
    }

    /**
     * Create a draft ShiftAssignment row.
     */
    private function createDraftAssignment(
        ShiftRequirement $requirement,
        EmployeeProfile $employee,
        User $actor,
        string $source,
    ): ShiftAssignment {
        $assignment = new ShiftAssignment();
        $assignment->forceFill([
            'shift_requirement_id' => $requirement->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'status' => ShiftAssignmentStatusEnum::Draft->value,
            'source' => $source,
            'assigned_by' => $actor->getKey(),
        ])->save();

        return $assignment;
    }

    /**
     * Load the parent schedule and trigger a conflict recompute.
     */
    private function recomputeForRequirement(ShiftRequirement $requirement): void
    {
        $schedule = Schedule::query()->find($requirement->getScheduleId());
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
