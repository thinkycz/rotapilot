<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\AvailabilitySourceEnum;
use App\Enums\AvailabilityTypeEnum;
use App\Enums\ShiftSourceEnum;
use App\Models\AgentActionProposal;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\StoreBusinessHour;
use App\Models\User;
use App\Services\Scheduling\AssignmentService;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class AgentProposalApplyService
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly ConflictDetectionService $conflicts,
    ) {}

    /**
     * Apply a pending proposal.
     *
     * @param array<int, int>|null $selectedIndexes
     */
    public function apply(AgentActionProposal $proposal, User $actor, array|null $selectedIndexes = null): AgentActionProposal
    {
        $this->assertPendingOwnProposal($proposal, $actor);

        $affectedScheduleIds = [];
        $appliedActions = [];

        try {
            DB::transaction(function () use ($actor, &$affectedScheduleIds, &$appliedActions, $proposal, $selectedIndexes): void {
                foreach ($proposal->getActions() as $index => $action) {
                    if ($selectedIndexes !== null && !\in_array($index, $selectedIndexes, true)) {
                        continue;
                    }
                    $result = $this->applyAction($actor, $action);
                    $result['action_index'] = $index;
                    $appliedActions[] = $result;

                    $scheduleId = $result['schedule_id'] ?? null;
                    if (\is_int($scheduleId)) {
                        $affectedScheduleIds[] = $scheduleId;
                    }
                }
            });
        } catch (Throwable $throwable) {
            $proposal->forceFill([
                'status' => AgentActionProposal::STATUS_FAILED,
                'result' => [
                    'error' => $throwable->getMessage(),
                ],
            ])->save();

            throw $throwable;
        }

        $conflicts = $this->detectConflicts(\array_values(\array_unique($affectedScheduleIds)));

        $proposal->forceFill([
            'status' => AgentActionProposal::STATUS_APPLIED,
            'result' => [
                'applied_actions' => $appliedActions,
                'conflicts' => $conflicts,
            ],
        ])->save();

        return $proposal;
    }

    /**
     * Reject a pending proposal.
     */
    public function reject(AgentActionProposal $proposal, User $actor): AgentActionProposal
    {
        $this->assertPendingOwnProposal($proposal, $actor);

        $proposal->forceFill([
            'status' => AgentActionProposal::STATUS_REJECTED,
            'result' => null,
        ])->save();

        return $proposal;
    }

    /**
     * Apply one normalized action.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function applyAction(User $actor, array $action): array
    {
        $type = $this->string($action, 'type');

        return match ($type) {
            'store.create' => $this->createStore($actor, $action),
            'store.update' => $this->updateStore($actor, $action),
            'availability.create' => $this->createAvailability($actor, $action),
            'availability.update' => $this->updateAvailability($actor, $action),
            'availability.delete' => $this->deleteAvailability($actor, $action),
            'shift.create' => $this->createShift($actor, $action),
            'shift.update' => $this->updateShift($actor, $action),
            'shift.delete' => $this->deleteShift($actor, $action),
            'shift.assign' => $this->assignShift($actor, $action),
            'shift.unassign' => $this->unassignShift($actor, $action),
            'shift.autofill' => $this->autofillShift($actor, $action),
            'shift.assignment.update' => $this->updateShiftAssignment($actor, $action),
            'business_hours.update' => $this->updateBusinessHours($actor, $action),
            default => throw new RuntimeException('Unsupported action type: ' . $type),
        };
    }

    /**
     * Create store.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function createStore(User $actor, array $action): array
    {
        Authorization::mustCreateStore($actor);

        $store = new Store();
        $store->forceFill([
            'name' => $this->string($action, 'name'),
            'address' => $this->nullableString($action, 'address'),
            'city' => $this->nullableString($action, 'city'),
            'timezone' => $this->string($action, 'timezone'),
            'is_active' => $this->bool($action, 'is_active', true),
        ])->save();

        DB::table('store_manager_store')->updateOrInsert(
            ['user_id' => $actor->getKey(), 'store_id' => $store->getKey()],
            ['updated_at' => \now(), 'created_at' => \now()],
        );

        return ['type' => 'store.create', 'store_id' => $store->getKey()];
    }

    /**
     * Update store.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function updateStore(User $actor, array $action): array
    {
        $store = $this->managedStore($actor, $this->int($action, 'store_id'));
        $store->forceFill([
            'name' => $this->string($action, 'name'),
            'address' => $this->nullableString($action, 'address'),
            'city' => $this->nullableString($action, 'city'),
            'timezone' => $this->string($action, 'timezone'),
            'is_active' => $this->bool($action, 'is_active', true),
        ])->save();

        return ['type' => 'store.update', 'store_id' => $store->getKey()];
    }

    /**
     * Create availability.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function createAvailability(User $actor, array $action): array
    {
        $employee = $this->managedEmployee($actor, $this->int($action, 'employee_profile_id'));
        $storeId = $this->nullableInt($action, 'store_id');

        if ($storeId !== null) {
            $this->managedStore($actor, $storeId);
        }

        $type = $this->availabilityType($action);
        $times = $this->availabilityTimes($type, $action);

        $availability = EmployeeAvailability::query()->create([
            'employee_profile_id' => $employee->getKey(),
            'store_id' => $storeId,
            'date' => $this->string($action, 'date'),
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
            'type' => $type,
            'note' => $this->nullableString($action, 'note'),
            'source' => AvailabilitySourceEnum::Ai->value,
            'created_by' => $actor->getKey(),
        ]);

        return ['type' => 'availability.create', 'availability_id' => $availability->getKey()];
    }

    /**
     * Update availability.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function updateAvailability(User $actor, array $action): array
    {
        $availability = $this->managedAvailability($actor, $this->int($action, 'availability_id'));
        $type = $this->availabilityType($action);
        $times = $this->availabilityTimes($type, $action);

        $availability->forceFill([
            'type' => $type,
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
            'note' => $this->nullableString($action, 'note'),
        ])->save();

        return ['type' => 'availability.update', 'availability_id' => $availability->getKey()];
    }

    /**
     * Delete availability.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function deleteAvailability(User $actor, array $action): array
    {
        $availability = $this->managedAvailability($actor, $this->int($action, 'availability_id'));
        $id = $availability->getKey();
        $availability->delete();

        return ['type' => 'availability.delete', 'availability_id' => $id];
    }

    /**
     * Create shift.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function createShift(User $actor, array $action): array
    {
        $schedule = $this->managedSchedule($actor, $this->int($action, 'schedule_id'));

        $shift = new ShiftRequirement();
        $shift->forceFill([
            'schedule_id' => $schedule->getKey(),
            'store_id' => $schedule->getStoreId(),
            'date' => $this->string($action, 'date'),
            'start_time' => $this->validTimeRange($action)['start_time'],
            'end_time' => $this->validTimeRange($action)['end_time'],
            'role_label' => $this->nullableString($action, 'role_label'),
            'note' => $this->nullableString($action, 'note'),
            'source' => ShiftSourceEnum::Ai->value,
            'created_by' => $actor->getKey(),
        ])->save();

        foreach ($this->employeeIds($action) as $employeeId) {
            $this->assignments->assignWithoutRecompute($shift, $this->managedEmployee($actor, $employeeId), $actor);
        }

        return ['type' => 'shift.create', 'shift_requirement_id' => $shift->getKey(), 'schedule_id' => $schedule->getKey()];
    }

    /**
     * Update shift.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function updateShift(User $actor, array $action): array
    {
        $shift = $this->managedShiftRequirement($actor, $this->int($action, 'shift_requirement_id'));
        $times = $this->validTimeRange($action);

        $shift->forceFill([
            'date' => $this->string($action, 'date'),
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
            'role_label' => $this->nullableString($action, 'role_label'),
            'note' => $this->nullableString($action, 'note'),
        ])->save();

        return ['type' => 'shift.update', 'shift_requirement_id' => $shift->getKey(), 'schedule_id' => $shift->getScheduleId()];
    }

    /**
     * Delete shift.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function deleteShift(User $actor, array $action): array
    {
        $shift = $this->managedShiftRequirement($actor, $this->int($action, 'shift_requirement_id'));
        $id = $shift->getKey();
        $scheduleId = $shift->getScheduleId();
        $shift->delete();

        return ['type' => 'shift.delete', 'shift_requirement_id' => $id, 'schedule_id' => $scheduleId];
    }

    /**
     * Assign shift.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function assignShift(User $actor, array $action): array
    {
        $shift = $this->managedShiftRequirement($actor, $this->int($action, 'shift_requirement_id'));
        $employee = $this->managedEmployee($actor, $this->int($action, 'employee_profile_id'));
        $times = $this->validTimeRange($action);

        if ($times['start_time'] < \mb_substr($shift->getStartTime(), 0, 5) || $times['end_time'] > \mb_substr($shift->getEndTime(), 0, 5)) {
            throw new RuntimeException('Assignment time must be within the shift hours.');
        }

        $assignment = $this->assignments->assign($shift, $employee, $actor, $times['start_time'], $times['end_time']);

        return ['type' => 'shift.assign', 'shift_assignment_id' => $assignment->getKey(), 'schedule_id' => $shift->getScheduleId()];
    }

    /**
     * Unassign shift.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function unassignShift(User $actor, array $action): array
    {
        $assignment = $this->managedShiftAssignment($actor, $this->int($action, 'shift_assignment_id'));
        $scheduleId = $assignment->getShiftRequirement()->getScheduleId();
        $id = $assignment->getKey();
        $this->assignments->unassign($assignment);

        return ['type' => 'shift.unassign', 'shift_assignment_id' => $id, 'schedule_id' => $scheduleId];
    }

    /**
     * Update shift assignment.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function updateShiftAssignment(User $actor, array $action): array
    {
        $assignment = $this->managedShiftAssignment($actor, $this->int($action, 'shift_assignment_id'));
        $employee = $this->managedEmployee($actor, $this->int($action, 'employee_profile_id'));
        $startTime = $this->string($action, 'start_time');
        $endTime = $this->string($action, 'end_time');

        $shift = $assignment->getShiftRequirement();
        if ($startTime < \mb_substr($shift->getStartTime(), 0, 5) || $endTime > \mb_substr($shift->getEndTime(), 0, 5)) {
            throw new RuntimeException('Assignment time must be within the shift hours.');
        }

        $assignment->forceFill([
            'employee_profile_id' => $employee->getKey(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'assigned_by' => $actor->getKey(),
        ])->save();

        return ['type' => 'shift.assignment.update', 'shift_assignment_id' => $assignment->getKey(), 'schedule_id' => $shift->getScheduleId()];
    }

    /**
     * Auto-fill shift.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function autofillShift(User $actor, array $action): array
    {
        $shift = $this->managedShiftRequirement($actor, $this->int($action, 'shift_requirement_id'));
        $created = $this->assignments->autoFill($shift, $actor);

        return [
            'type' => 'shift.autofill',
            'shift_requirement_id' => $shift->getKey(),
            'schedule_id' => $shift->getScheduleId(),
            'assignment_count' => \count($created),
        ];
    }

    /**
     * Update store business hours.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function updateBusinessHours(User $actor, array $action): array
    {
        $store = $this->managedStore($actor, $this->int($action, 'store_id'));
        $hours = $this->businessHours($action);

        foreach ($hours as $hour) {
            StoreBusinessHour::query()->updateOrCreate(
                ['store_id' => $store->getKey(), 'day_of_week' => $hour['day_of_week']],
                [
                    'opens_at' => $hour['opens_at'],
                    'closes_at' => $hour['closes_at'],
                    'is_closed' => $hour['is_closed'],
                ],
            );
        }

        return ['type' => 'business_hours.update', 'store_id' => $store->getKey()];
    }

    /**
     * Detect conflicts for affected schedules.
     *
     * @param array<int, int> $scheduleIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectConflicts(array $scheduleIds): array
    {
        $rows = [];

        foreach ($scheduleIds as $scheduleId) {
            $schedule = Schedule::query()->find($scheduleId);
            if (!$schedule instanceof Schedule) {
                continue;
            }

            $rows[] = [
                'schedule_id' => $schedule->getKey(),
                'conflicts' => $this->conflicts->detect($schedule),
            ];
        }

        return $rows;
    }

    /**
     * Assert pending own proposal.
     */
    private function assertPendingOwnProposal(AgentActionProposal $proposal, User $actor): void
    {
        if (!$actor->isStoreManager() || $proposal->getUserId() !== $actor->getKey()) {
            throw new AccessDeniedHttpException('You cannot manage this proposal.');
        }

        if (!$proposal->isPending()) {
            throw new RuntimeException('This proposal is no longer pending.');
        }
    }

    /**
     * Find managed store.
     */
    private function managedStore(User $actor, int $id): Store
    {
        $store = Store::query()->find($id);
        if (!$store instanceof Store || !Authorization::canManageStore($actor, $store)) {
            throw new AccessDeniedHttpException('You cannot manage this store.');
        }

        return $store;
    }

    /**
     * Find managed schedule.
     */
    private function managedSchedule(User $actor, int $id): Schedule
    {
        $schedule = Schedule::query()->find($id);
        if (!$schedule instanceof Schedule || !Authorization::canManageSchedule($actor, $schedule)) {
            throw new AccessDeniedHttpException('You cannot manage this schedule.');
        }

        return $schedule;
    }

    /**
     * Find managed employee.
     */
    private function managedEmployee(User $actor, int $id): EmployeeProfile
    {
        $employee = EmployeeProfile::query()->find($id);
        if (!$employee instanceof EmployeeProfile || !Authorization::canViewEmployee($actor, $employee)) {
            throw new AccessDeniedHttpException('You cannot manage this employee.');
        }

        return $employee;
    }

    /**
     * Find managed availability.
     */
    private function managedAvailability(User $actor, int $id): EmployeeAvailability
    {
        $availability = EmployeeAvailability::query()->with('employeeProfile')->find($id);
        if (!$availability instanceof EmployeeAvailability || !Authorization::canViewEmployee($actor, $availability->getEmployeeProfile())) {
            throw new AccessDeniedHttpException('You cannot manage this availability.');
        }

        return $availability;
    }

    /**
     * Find managed shift requirement.
     */
    private function managedShiftRequirement(User $actor, int $id): ShiftRequirement
    {
        $shift = ShiftRequirement::query()->with('schedule')->find($id);
        if (!$shift instanceof ShiftRequirement || !Authorization::canManageShiftRequirement($actor, $shift)) {
            throw new AccessDeniedHttpException('You cannot manage this shift.');
        }

        return $shift;
    }

    /**
     * Find managed shift assignment.
     */
    private function managedShiftAssignment(User $actor, int $id): ShiftAssignment
    {
        $assignment = ShiftAssignment::query()->with('shiftRequirement.schedule')->find($id);
        if (!$assignment instanceof ShiftAssignment || !Authorization::canManageShiftRequirement($actor, $assignment->getShiftRequirement())) {
            throw new AccessDeniedHttpException('You cannot manage this assignment.');
        }

        return $assignment;
    }

    /**
     * Validate availability type.
     *
     * @param array<string, mixed> $action
     */
    private function availabilityType(array $action): string
    {
        $type = $this->string($action, 'availability_type');

        if (!\in_array($type, AvailabilityTypeEnum::values(), true)) {
            throw new RuntimeException('Invalid availability type.');
        }

        return $type;
    }

    /**
     * Normalize availability times.
     *
     * @param array<string, mixed> $action
     *
     * @return array{start_time: string|null, end_time: string|null}
     */
    private function availabilityTimes(string $type, array $action): array
    {
        if ($type === AvailabilityTypeEnum::Unavailable->value) {
            return ['start_time' => null, 'end_time' => null];
        }

        $startTime = $this->nullableString($action, 'start_time');
        $endTime = $this->nullableString($action, 'end_time');

        if ($startTime === null || $endTime === null || $startTime >= $endTime) {
            throw new RuntimeException('Available and backup availability need valid start and end times.');
        }

        return ['start_time' => $startTime, 'end_time' => $endTime];
    }

    /**
     * Validate time range.
     *
     * @param array<string, mixed> $action
     *
     * @return array{start_time: string, end_time: string}
     */
    private function validTimeRange(array $action): array
    {
        $startTime = $this->string($action, 'start_time');
        $endTime = $this->string($action, 'end_time');

        if ($startTime >= $endTime) {
            throw new RuntimeException('Start time must be before end time.');
        }

        return ['start_time' => $startTime, 'end_time' => $endTime];
    }

    /**
     * Employee ids from action.
     *
     * @param array<string, mixed> $action
     *
     * @return array<int, int>
     */
    private function employeeIds(array $action): array
    {
        $raw = $action['employee_profile_ids'] ?? [];
        if (!\is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            if (\is_int($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Business-hours rows from normalized proposal action.
     *
     * @param array<string, mixed> $action
     *
     * @return array<int, array{day_of_week: int, opens_at: string|null, closes_at: string|null, is_closed: bool}>
     */
    private function businessHours(array $action): array
    {
        $raw = $action['hours'] ?? null;
        if (!\is_array($raw)) {
            throw new RuntimeException('Missing business-hours rows.');
        }

        $rows = [];
        foreach ($raw as $rawRow) {
            if (!\is_array($rawRow)) {
                throw new RuntimeException('Invalid business-hours row.');
            }

            $row = [];
            foreach ($rawRow as $key => $value) {
                if (\is_string($key)) {
                    $row[$key] = $value;
                }
            }

            $rows[] = [
                'day_of_week' => $this->int($row, 'day_of_week'),
                'opens_at' => $this->nullableString($row, 'opens_at'),
                'closes_at' => $this->nullableString($row, 'closes_at'),
                'is_closed' => $this->bool($row, 'is_closed', false),
            ];
        }

        return $rows;
    }

    /**
     * String input.
     *
     * @param array<string, mixed> $action
     */
    private function string(array $action, string $key): string
    {
        $value = $action[$key] ?? null;

        if (!\is_string($value) || $value === '') {
            throw new RuntimeException('Missing string field: ' . $key);
        }

        return $value;
    }

    /**
     * Nullable string input.
     *
     * @param array<string, mixed> $action
     */
    private function nullableString(array $action, string $key): string|null
    {
        $value = $action[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Integer input.
     *
     * @param array<string, mixed> $action
     */
    private function int(array $action, string $key): int
    {
        $value = $action[$key] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        throw new RuntimeException('Missing integer field: ' . $key);
    }

    /**
     * Nullable integer input.
     *
     * @param array<string, mixed> $action
     */
    private function nullableInt(array $action, string $key): int|null
    {
        $value = $action[$key] ?? null;

        return \is_int($value) ? $value : null;
    }

    /**
     * Boolean input.
     *
     * @param array<string, mixed> $action
     */
    private function bool(array $action, string $key, bool $default): bool
    {
        $value = $action[$key] ?? $default;

        return \is_bool($value) ? $value : (bool) $value;
    }
}
