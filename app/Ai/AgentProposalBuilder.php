<?php

declare(strict_types=1);

namespace App\Ai;

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
        foreach ($rawActions as $rawAction) {
            if (!\is_array($rawAction)) {
                continue;
            }

            $actions[] = $this->normalizeAction($user, $rawAction);
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
     *
     * @return array<string, mixed>
     */
    private function normalizeAction(User $user, array $action): array
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
            'shift.assign' => $this->shiftAssign($user, $type, $action),
            'shift.unassign' => [
                'type' => $type,
                'shift_assignment_id' => $this->managedShiftAssignment($user, $this->int($action, 'shift_assignment_id'))->getKey(),
            ],
            'shift.autofill' => [
                'type' => $type,
                'shift_requirement_id' => $this->managedShiftRequirement($user, $this->int($action, 'shift_requirement_id'))->getKey(),
            ],
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
            'availability_type' => $this->string($action, 'availability_type', 'type'),
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
            'availability_type' => $this->string($action, 'availability_type', 'type'),
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
     *
     * @return array<string, mixed>
     */
    private function shiftAssign(User $user, string $type, array $action): array
    {
        $requirement = $this->managedShiftRequirement($user, $this->int($action, 'shift_requirement_id'));
        $employee = $this->managedEmployee($user, $this->int($action, 'employee_profile_id'));

        return [
            'type' => $type,
            'shift_requirement_id' => $requirement->getKey(),
            'employee_profile_id' => $employee->getKey(),
            'start_time' => $this->string($action, 'start_time'),
            'end_time' => $this->string($action, 'end_time'),
        ];
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
