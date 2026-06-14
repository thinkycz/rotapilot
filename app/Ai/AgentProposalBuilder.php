<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\AvailabilityTypeEnum;
use App\Enums\ShiftAssignmentStatusEnum;
use App\Models\AgentActionProposal;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thinkycz\LaravelCore\Support\Typer;

class AgentProposalBuilder
{
    /**
     * Supported action types.
     *
     * @var array<int, string>
     */
    private const ACTION_TYPES = [
        'store.create',
        'store.update',
        'availability.create',
        'availability.update',
        'availability.delete',
        'shift.create',
        'shift.update',
        'shift.delete',
        'shift.assign',
        'shift.unassign',
        'shift.autofill',
        'shift.assignment.update',
        'business_hours.update',
    ];

    /**
     * Create a pending proposal.
     *
     * @param array<mixed> $rawActions
     */
    public function create(User $user, string $conversationId, string $summary, array $rawActions): AgentActionProposal
    {
        if (!$user->isStoreManager()) {
            throw new AccessDeniedHttpException('Only store managers can propose scheduling changes.');
        }

        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->getKey())
            ->first();

        if (!$conversation instanceof Conversation) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $actions = [];
        /** @var array{unassigned_assignment_ids: array<int, true>, ...} $proposalState */
        $proposalState = [
            'unassigned_assignment_ids' => [],
        ];

        foreach ($rawActions as $rawAction) {
            if (!\is_array($rawAction)) {
                continue;
            }

            $actions[] = $this->normalizeAction($user, $rawAction, $proposalState);
        }

        if (\count($actions) === 0) {
            throw new NotFoundHttpException('No supported actions were proposed.');
        }

        return Typer::assertInstance(AgentActionProposal::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $user->getKey(),
            'status' => AgentActionProposal::STATUS_PENDING,
            'summary' => Str::limit($summary, 500, ''),
            'actions' => $actions,
            'result' => null,
        ]), AgentActionProposal::class);
    }

    /**
     * Normalize and scope-check one action.
     *
     * @param array<mixed> $action
     * @param array{unassigned_assignment_ids: array<int, true>, ...} $proposalState
     *
     * @return array<string, mixed>
     */
    private function normalizeAction(User $user, array $action, array &$proposalState): array
    {
        $action = $this->stringKeyed($action);
        $type = Typer::assertString($action['type'] ?? null);

        if (!\in_array($type, self::ACTION_TYPES, true)) {
            throw new NotFoundHttpException('Unsupported action type: ' . $type);
        }

        return match ($type) {
            'store.create' => [
                'type' => $type,
                'name' => $this->string($action, 'name'),
                'address' => $this->nullableString($action, 'address'),
                'city' => $this->nullableString($action, 'city'),
                'timezone' => $this->string($action, 'timezone'),
                'is_active' => $this->bool($action, 'is_active', true),
            ],
            'store.update' => [
                'type' => $type,
                'store_id' => $this->managedStore($user, $this->int($action, 'store_id'))->getKey(),
                'name' => $this->string($action, 'name'),
                'address' => $this->nullableString($action, 'address'),
                'city' => $this->nullableString($action, 'city'),
                'timezone' => $this->string($action, 'timezone'),
                'is_active' => $this->bool($action, 'is_active', true),
            ],
            'availability.create' => $this->availabilityCreate($user, $type, $action),
            'availability.update' => $this->availabilityUpdate($user, $type, $action),
            'availability.delete' => [
                'type' => $type,
                'availability_id' => $this->managedAvailability($user, $this->int($action, 'availability_id'))->getKey(),
            ],
            'shift.create' => $this->shiftCreate($user, $type, $action),
            'shift.update' => $this->shiftUpdate($user, $type, $action),
            'shift.delete' => [
                'type' => $type,
                'shift_requirement_id' => $this->managedShiftRequirement($user, $this->int($action, 'shift_requirement_id'))->getKey(),
            ],
            'shift.assign' => $this->shiftAssign($user, $type, $action, $proposalState),
            'shift.unassign' => $this->shiftUnassign($user, $type, $action, $proposalState),
            'shift.autofill' => [
                'type' => $type,
                'shift_requirement_id' => $this->managedShiftRequirement($user, $this->int($action, 'shift_requirement_id'))->getKey(),
            ],
            'shift.assignment.update' => $this->shiftAssignmentUpdate($user, $type, $action, $proposalState),
            'business_hours.update' => $this->businessHoursUpdate($user, $type, $action),
        };
    }

    /**
     * Keep only string-keyed action data.
     *
     * @param array<mixed> $action
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $action): array
    {
        $normalized = [];

        foreach ($action as $key => $value) {
            if (\is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Normalize availability create.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function availabilityCreate(User $user, string $type, array $action): array
    {
        $employee = $this->managedEmployee($user, $this->int($action, 'employee_profile_id'));
        $storeId = $this->nullableInt($action, 'store_id');

        if ($storeId !== null) {
            $this->managedStore($user, $storeId);
        }

        return [
            'type' => $type,
            'employee_profile_id' => $employee->getKey(),
            'store_id' => $storeId,
            'date' => $this->string($action, 'date'),
            'availability_type' => $this->availabilityTypeField($action),
            'start_time' => $this->nullableString($action, 'start_time'),
            'end_time' => $this->nullableString($action, 'end_time'),
            'note' => $this->nullableString($action, 'note'),
        ];
    }

    /**
     * Normalize availability update.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function availabilityUpdate(User $user, string $type, array $action): array
    {
        $availability = $this->managedAvailability($user, $this->int($action, 'availability_id'));

        return [
            'type' => $type,
            'availability_id' => $availability->getKey(),
            'availability_type' => $this->availabilityTypeField($action),
            'start_time' => $this->nullableString($action, 'start_time'),
            'end_time' => $this->nullableString($action, 'end_time'),
            'note' => $this->nullableString($action, 'note'),
        ];
    }

    /**
     * Normalize shift create.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function shiftCreate(User $user, string $type, array $action): array
    {
        $schedule = $this->managedSchedule($user, $this->int($action, 'schedule_id'));

        return [
            'type' => $type,
            'schedule_id' => $schedule->getKey(),
            'date' => $this->string($action, 'date'),
            'start_time' => $this->string($action, 'start_time'),
            'end_time' => $this->string($action, 'end_time'),
            'role_label' => $this->nullableString($action, 'role_label'),
            'note' => $this->nullableString($action, 'note'),
            'employee_profile_ids' => $this->managedEmployeeIds($user, $action['employee_profile_ids'] ?? []),
        ];
    }

    /**
     * Normalize shift update.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function shiftUpdate(User $user, string $type, array $action): array
    {
        $requirement = $this->managedShiftRequirement($user, $this->int($action, 'shift_requirement_id'));

        return [
            'type' => $type,
            'shift_requirement_id' => $requirement->getKey(),
            'date' => $this->string($action, 'date'),
            'start_time' => $this->string($action, 'start_time'),
            'end_time' => $this->string($action, 'end_time'),
            'role_label' => $this->nullableString($action, 'role_label'),
            'note' => $this->nullableString($action, 'note'),
        ];
    }

    /**
     * Normalize shift assignment.
     *
     * @param array<string, mixed> $action
     * @param array{unassigned_assignment_ids: array<int, true>, ...} $proposalState
     *
     * @return array<string, mixed>
     */
    private function shiftAssign(User $user, string $type, array $action, array $proposalState): array
    {
        $requirement = $this->managedShiftRequirement($user, $this->int($action, 'shift_requirement_id'));
        $employee = $this->managedEmployee($user, $this->int($action, 'employee_profile_id'));
        $startTime = $this->timeString($this->nullableString($action, 'start_time') ?? $requirement->getStartTime());
        $endTime = $this->timeString($this->nullableString($action, 'end_time') ?? $requirement->getEndTime());

        if ($startTime >= $endTime) {
            throw new RuntimeException('Assignment start_time must be before end_time.');
        }

        if ($startTime < $this->timeString($requirement->getStartTime()) || $endTime > $this->timeString($requirement->getEndTime())) {
            throw new RuntimeException('Assignment time must be within the shift hours.');
        }

        $this->assertNoDuplicateAssignmentStart($requirement, $employee, $startTime, $proposalState);

        return [
            'type' => $type,
            'shift_requirement_id' => $requirement->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    /**
     * Normalize shift unassignment and remember it for same-batch replacements.
     *
     * @param array<string, mixed> $action
     * @param array{unassigned_assignment_ids: array<int, true>, ...} $proposalState
     *
     * @return array<string, mixed>
     */
    private function shiftUnassign(User $user, string $type, array $action, array &$proposalState): array
    {
        $assignment = $this->managedShiftAssignment($user, $this->int($action, 'shift_assignment_id'));

        $proposalState['unassigned_assignment_ids'][$assignment->getKey()] = true;

        return [
            'type' => $type,
            'shift_assignment_id' => $assignment->getKey(),
        ];
    }

    /**
     * Normalize shift assignment update.
     *
     * @param array<string, mixed> $action
     * @param array{unassigned_assignment_ids: array<int, true>, ...} $proposalState
     *
     * @return array<string, mixed>
     */
    private function shiftAssignmentUpdate(User $user, string $type, array $action, array $proposalState): array
    {
        $assignment = $this->managedShiftAssignment($user, $this->int($action, 'shift_assignment_id'));
        $requirement = $assignment->getShiftRequirement();

        $employeeId = $this->nullableInt($action, 'employee_profile_id');
        $employee = $employeeId !== null ? $this->managedEmployee($user, $employeeId) : $assignment->getEmployeeProfile();

        $startTime = $this->nullableString($action, 'start_time');
        $startTime = $startTime !== null ? $this->timeString($startTime) : $this->timeString($assignment->getStartTime());

        $endTime = $this->nullableString($action, 'end_time');
        $endTime = $endTime !== null ? $this->timeString($endTime) : $this->timeString($assignment->getEndTime());

        if ($startTime >= $endTime) {
            throw new RuntimeException('Assignment start_time must be before end_time.');
        }

        if ($startTime < $this->timeString($requirement->getStartTime()) || $endTime > $this->timeString($requirement->getEndTime())) {
            throw new RuntimeException('Assignment time must be within the shift hours.');
        }

        $this->assertNoDuplicateAssignmentStart($requirement, $employee, $startTime, $proposalState, $assignment->getKey());

        return [
            'type' => $type,
            'shift_assignment_id' => $assignment->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    /**
     * Normalize store business-hours update.
     *
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function businessHoursUpdate(User $user, string $type, array $action): array
    {
        $store = $this->managedStore($user, $this->int($action, 'store_id'));
        $rawHours = $action['hours'] ?? null;
        if (!\is_array($rawHours)) {
            throw new RuntimeException('business_hours.update requires an hours array.');
        }

        $hours = [];
        $seenDays = [];

        foreach ($rawHours as $rawHour) {
            if (!\is_array($rawHour)) {
                throw new RuntimeException('Each business-hours row must be an object.');
            }

            $hour = $this->stringKeyed($rawHour);
            $dayOfWeek = $this->int($hour, 'day_of_week');
            if ($dayOfWeek < 1 || $dayOfWeek > 7) {
                throw new RuntimeException('day_of_week must be between 1 and 7.');
            }

            if (isset($seenDays[$dayOfWeek])) {
                throw new RuntimeException('Duplicate business-hours day: ' . $dayOfWeek . '.');
            }
            $seenDays[$dayOfWeek] = true;

            $isClosed = $this->bool($hour, 'is_closed', false);
            $opensAt = $this->nullableTimeString($hour, 'opens_at');
            $closesAt = $this->nullableTimeString($hour, 'closes_at');

            if ($isClosed) {
                $opensAt = null;
                $closesAt = null;
            } else {
                if ($opensAt === null || $closesAt === null) {
                    throw new RuntimeException('Open business-hours days require opens_at and closes_at.');
                }

                if ($closesAt <= $opensAt) {
                    throw new RuntimeException('Business-hours closes_at must be after opens_at.');
                }
            }

            $hours[] = [
                'day_of_week' => $dayOfWeek,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'is_closed' => $isClosed,
            ];
        }

        if (\count($hours) === 0) {
            throw new RuntimeException('business_hours.update requires at least one hours row.');
        }

        \usort($hours, static fn(array $a, array $b): int => $a['day_of_week'] <=> $b['day_of_week']);

        return [
            'type' => $type,
            'store_id' => $store->getKey(),
            'hours' => $hours,
        ];
    }

    /**
     * Guard the database uniqueness constraint before a proposal is created.
     *
     * @param array{unassigned_assignment_ids: array<int, true>, ...} $proposalState
     */
    private function assertNoDuplicateAssignmentStart(
        ShiftRequirement $requirement,
        EmployeeProfile $employee,
        string $startTime,
        array $proposalState,
        int|null $excludeAssignmentId = null,
    ): void {
        $query = ShiftAssignment::query()
            ->where('shift_requirement_id', $requirement->getKey())
            ->where('employee_profile_id', $employee->getKey())
            ->where('start_time', $startTime)
            ->where('status', '!=', ShiftAssignmentStatusEnum::Cancelled->value);

        if ($excludeAssignmentId !== null) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        $existing = $query->first();

        if (!$existing instanceof ShiftAssignment) {
            return;
        }

        if (($proposalState['unassigned_assignment_ids'][$existing->getKey()] ?? false) === true) {
            return;
        }

        throw new RuntimeException(
            'Employee profile ID ' . $employee->getKey() .
            ' already has assignment ID ' . $existing->getKey() .
            ' for shift requirement ID ' . $requirement->getKey() .
            ' starting at ' . $startTime .
            '. Use shift.unassign for the existing assignment before creating a replacement assignment.',
        );
    }

    /**
     * Find managed store.
     */
    private function managedStore(User $user, int $id): Store
    {
        $store = Store::query()->find($id);
        if (!$store instanceof Store || !Authorization::canManageStore($user, $store)) {
            throw new AccessDeniedHttpException('You cannot manage store ID ' . $id . '.');
        }

        return $store;
    }

    /**
     * Find managed schedule.
     */
    private function managedSchedule(User $user, int $id): Schedule
    {
        $schedule = Schedule::query()->find($id);
        if (!$schedule instanceof Schedule || !Authorization::canManageSchedule($user, $schedule)) {
            throw new AccessDeniedHttpException('You cannot manage schedule ID ' . $id . '.');
        }

        return $schedule;
    }

    /**
     * Find managed employee.
     */
    private function managedEmployee(User $user, int $id): EmployeeProfile
    {
        $employee = EmployeeProfile::query()->find($id);
        if (!$employee instanceof EmployeeProfile || !Authorization::canViewEmployee($user, $employee)) {
            throw new AccessDeniedHttpException('You cannot manage employee profile ID ' . $id . '.');
        }

        return $employee;
    }

    /**
     * Find managed availability.
     */
    private function managedAvailability(User $user, int $id): EmployeeAvailability
    {
        $availability = EmployeeAvailability::query()->with('employeeProfile')->find($id);
        if (!$availability instanceof EmployeeAvailability || !Authorization::canViewEmployee($user, $availability->getEmployeeProfile())) {
            throw new AccessDeniedHttpException('You cannot manage availability ID ' . $id . '.');
        }

        return $availability;
    }

    /**
     * Find managed shift requirement.
     */
    private function managedShiftRequirement(User $user, int $id): ShiftRequirement
    {
        $requirement = ShiftRequirement::query()->with('schedule')->find($id);
        if (!$requirement instanceof ShiftRequirement || !Authorization::canManageShiftRequirement($user, $requirement)) {
            throw new AccessDeniedHttpException('You cannot manage shift requirement ID ' . $id . '.');
        }

        return $requirement;
    }

    /**
     * Find managed shift assignment.
     */
    private function managedShiftAssignment(User $user, int $id): ShiftAssignment
    {
        $assignment = ShiftAssignment::query()->with('shiftRequirement.schedule')->find($id);
        if (!$assignment instanceof ShiftAssignment || !Authorization::canManageShiftRequirement($user, $assignment->getShiftRequirement())) {
            throw new AccessDeniedHttpException('You cannot manage shift assignment ID ' . $id . '.');
        }

        return $assignment;
    }

    /**
     * Normalize managed employee ids.
     *
     * @return array<int, int>
     */
    private function managedEmployeeIds(User $user, mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (\is_scalar($value)) {
                $ids[] = $this->managedEmployee($user, (int) $value)->getKey();
            }
        }

        return \array_values(\array_unique($ids));
    }

    /**
     * String input.
     *
     * @param array<string, mixed> $action
     */
    private function string(array $action, string $key, string|null $fallbackKey = null): string
    {
        $value = $action[$key] ?? ($fallbackKey !== null ? ($action[$fallbackKey] ?? null) : null);

        return Typer::assertString($value);
    }

    /**
     * Read and validate availability_type.
     *
     * @param array<string, mixed> $action
     */
    private function availabilityTypeField(array $action): string
    {
        $value = $action['availability_type'] ?? null;

        if (\is_string($value) && \in_array($value, AvailabilityTypeEnum::values(), true)) {
            return $value;
        }

        throw new RuntimeException('availability_type must be one of: available, unavailable, backup.');
    }

    /**
     * Normalize AI-supplied times to the HH:MM shape used by web forms.
     */
    private function timeString(string $value): string
    {
        return \mb_substr($value, 0, 5);
    }

    /**
     * Nullable strict HH:MM time input.
     *
     * @param array<string, mixed> $action
     */
    private function nullableTimeString(array $action, string $key): string|null
    {
        $value = $this->nullableString($action, $key);
        if ($value === null) {
            return null;
        }

        if (\preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $value) !== 1) {
            throw new RuntimeException($key . ' must use HH:MM format.');
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

        return Typer::assertNullableString($value);
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

        if (\is_string($value) && \ctype_digit($value)) {
            return (int) $value;
        }

        return Typer::assertInt($value);
    }

    /**
     * Nullable integer input.
     *
     * @param array<string, mixed> $action
     */
    private function nullableInt(array $action, string $key): int|null
    {
        $value = $action[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && \ctype_digit($value)) {
            return (int) $value;
        }

        return Typer::assertNullableInt($value);
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
