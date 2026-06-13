<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\EmployeeProfile;
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
     * Constructor.
     */
    public function __construct(
        ScheduleGeneratorService $generator,
    ) {
        $this->generator = $generator;
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

            $existing = $this->findAssignment(
                $requirement->getKey(),
                $employee->getKey(),
                $requirement->getStartTime(),
                $requirement->getEndTime(),
            );
            if ($existing instanceof ShiftAssignment) {
                $created[] = $existing;

                continue;
            }

            $created[] = $this->createDraftAssignment(
                $requirement,
                $employee,
                $actor,
                'manual',
                $requirement->getStartTime(),
                $requirement->getEndTime(),
            );
        }

        return $created;
    }

    /**
     * Manually assign a single employee.
     */
    public function assign(
        ShiftRequirement $requirement,
        EmployeeProfile $employee,
        User $actor,
        string|null $startTime = null,
        string|null $endTime = null,
    ): ShiftAssignment {
        return $this->assignWithoutRecompute($requirement, $employee, $actor, $startTime, $endTime);
    }

    /**
     * Manually assign a single employee without recomputing conflicts.
     */
    public function assignWithoutRecompute(
        ShiftRequirement $requirement,
        EmployeeProfile $employee,
        User $actor,
        string|null $startTime = null,
        string|null $endTime = null,
    ): ShiftAssignment {
        $startTime ??= $requirement->getStartTime();
        $endTime ??= $requirement->getEndTime();

        $existing = $this->findAssignment($requirement->getKey(), $employee->getKey(), $startTime, $endTime);
        if ($existing instanceof ShiftAssignment) {
            return $existing;
        }

        return $this->createDraftAssignment($requirement, $employee, $actor, 'manual', $startTime, $endTime);
    }

    /**
     * Remove an assignment.
     */
    public function unassign(ShiftAssignment $assignment): void
    {
        $requirement = $assignment->shiftRequirement;
        $assignment->delete();
    }

    /**
     * Create a draft ShiftAssignment row.
     */
    private function createDraftAssignment(
        ShiftRequirement $requirement,
        EmployeeProfile $employee,
        User $actor,
        string $source,
        string $startTime,
        string $endTime,
    ): ShiftAssignment {
        $assignment = new ShiftAssignment();
        $assignment->forceFill([
            'shift_requirement_id' => $requirement->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => ShiftAssignmentStatusEnum::Draft->value,
            'source' => $source,
            'assigned_by' => $actor->getKey(),
        ])->save();

        return $assignment;
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
    private function findAssignment(
        int $requirementId,
        int $employeeId,
        string $startTime,
        string $endTime,
    ): ShiftAssignment|null {
        $rows = ShiftAssignment::query()
            ->getQuery()
            ->where('shift_requirement_id', $requirementId)
            ->where('employee_profile_id', $employeeId)
            ->where('start_time', $startTime)
            ->where('end_time', $endTime)
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
