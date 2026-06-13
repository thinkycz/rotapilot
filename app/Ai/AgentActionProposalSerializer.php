<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AgentActionProposal;
use App\Models\EmployeeAvailability;
use App\Models\EmployeeProfile;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequirement;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Throwable;

class AgentActionProposalSerializer
{
    /**
     * Serialize proposal for Inertia.
     *
     * @return array<string, mixed>
     */
    public static function serialize(AgentActionProposal $proposal): array
    {
        $createdAt = $proposal->getAttribute('created_at');

        return [
            'id' => $proposal->getKey(),
            'conversation_id' => $proposal->getConversationId(),
            'message_id' => $proposal->getMessageId(),
            'status' => $proposal->getStatus(),
            'summary' => $proposal->getSummary(),
            'actions' => self::summarizeActions($proposal->getActions()),
            'result' => $proposal->getResult(),
            'created_at' => $createdAt instanceof Carbon ? $createdAt->toIso8601String() : null,
        ];
    }

    /**
     * Serialize action summaries.
     *
     * @param array<int, array<string, mixed>> $actions
     *
     * @return array<int, array<string, mixed>>
     */
    public static function summarizeActions(array $actions): array
    {
        $employeeIds = [];
        $storeIds = [];
        $requirementIds = [];
        $assignmentIds = [];
        $availabilityIds = [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            if (!\is_string($type)) {
                continue;
            }

            if (isset($action['employee_profile_id'])) {
                $employeeIds[] = self::intVal($action['employee_profile_id']);
            }
            if (isset($action['employee_profile_ids']) && \is_array($action['employee_profile_ids'])) {
                foreach ($action['employee_profile_ids'] as $id) {
                    $employeeIds[] = self::intVal($id);
                }
            }
            if (isset($action['store_id'])) {
                $storeIds[] = self::intVal($action['store_id']);
            }
            if (isset($action['shift_requirement_id'])) {
                $requirementIds[] = self::intVal($action['shift_requirement_id']);
            }
            if (isset($action['shift_assignment_id'])) {
                $assignmentIds[] = self::intVal($action['shift_assignment_id']);
            }
            if (isset($action['availability_id'])) {
                $availabilityIds[] = self::intVal($action['availability_id']);
            }
        }

        $employeeIds = \array_values(\array_unique($employeeIds));
        $storeIds = \array_values(\array_unique($storeIds));
        $requirementIds = \array_values(\array_unique($requirementIds));
        $assignmentIds = \array_values(\array_unique($assignmentIds));
        $availabilityIds = \array_values(\array_unique($availabilityIds));

        $context = [
            'employees' => \count($employeeIds) > 0
                ? EmployeeProfile::query()->whereIn('id', $employeeIds)->get()->keyBy('id')->all()
                : [],
            'stores' => \count($storeIds) > 0
                ? Store::query()->whereIn('id', $storeIds)->get()->keyBy('id')->all()
                : [],
            'requirements' => \count($requirementIds) > 0
                ? ShiftRequirement::query()->whereIn('id', $requirementIds)->get()->keyBy('id')->all()
                : [],
            'assignments' => \count($assignmentIds) > 0
                ? ShiftAssignment::query()->with(['employeeProfile', 'shiftRequirement'])->whereIn('id', $assignmentIds)->get()->keyBy('id')->all()
                : [],
            'availabilities' => \count($availabilityIds) > 0
                ? EmployeeAvailability::query()->with('employeeProfile')->whereIn('id', $availabilityIds)->get()->keyBy('id')->all()
                : [],
        ];

        $summaries = [];
        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $summaries[] = [
                'type' => \is_string($type) ? $type : 'unknown',
                'label' => self::label($action, $context),
                'payload' => $action,
            ];
        }

        return $summaries;
    }

    /**
     * Human-readable action label.
     *
     * @param array<string, mixed> $action
     * @param array{employees: array<int|string, EmployeeProfile>, stores: array<int|string, Store>, requirements: array<int|string, ShiftRequirement>, assignments: array<int|string, ShiftAssignment>, availabilities: array<int|string, EmployeeAvailability>} $context
     */
    private static function label(array $action, array $context): string
    {
        $type = \is_string($action['type'] ?? null) ? $action['type'] : 'unknown';

        return match ($type) {
            'store.create' => 'Create store "' . self::string($action, 'name') . '"',
            'store.update' => 'Update store "' . self::resolveStoreName($action, $context) . '"',
            'availability.create' => 'Create availability (' . self::string($action, 'availability_type') . ') for ' . self::resolveEmployeeName($action, $context) . ' on ' . self::formatDate(self::string($action, 'date')) . (self::string($action, 'start_time') !== '' ? ' (' . self::string($action, 'start_time') . ' - ' . self::string($action, 'end_time') . ')' : ''),
            'availability.update' => 'Update availability for ' . self::resolveAvailabilityEmployeeName($action, $context),
            'availability.delete' => 'Delete availability for ' . self::resolveAvailabilityEmployeeName($action, $context),
            'shift.create' => 'Create shift on ' . self::formatDate(self::string($action, 'date')) . ' (' . self::string($action, 'start_time') . ' - ' . self::string($action, 'end_time') . ')',
            'shift.update' => 'Update shift #' . self::string($action, 'shift_requirement_id') . ' on ' . self::formatDate(self::string($action, 'date')) . ' (' . self::string($action, 'start_time') . ' - ' . self::string($action, 'end_time') . ')',
            'shift.delete' => 'Delete shift ' . self::resolveShiftDescription($action, $context),
            'shift.assign' => 'Assign ' . self::resolveEmployeeName($action, $context) . ' to shift ' . self::resolveShiftDescription($action, $context),
            'shift.unassign' => 'Remove assignment of ' . self::resolveAssignmentEmployeeName($action, $context) . ' from shift ' . self::resolveAssignmentShiftDescription($action, $context),
            'shift.autofill' => 'Auto-fill shift ' . self::resolveShiftDescription($action, $context),
            'shift.assignment.update' => self::labelShiftAssignmentUpdate($action, $context),
            default => $type,
        };
    }

    /**
     * Label for shift assignment update.
     *
     * @param array<string, mixed> $action
     * @param array{employees: array<int|string, EmployeeProfile>, stores: array<int|string, Store>, requirements: array<int|string, ShiftRequirement>, assignments: array<int|string, ShiftAssignment>, availabilities: array<int|string, EmployeeAvailability>} $context
     */
    private static function labelShiftAssignmentUpdate(array $action, array $context): string
    {
        $id = self::intVal($action['employee_profile_id'] ?? 0);
        $assignmentId = self::intVal($action['shift_assignment_id'] ?? 0);
        $assignment = $context['assignments'][$assignmentId] ?? null;

        $originalEmployeeName = self::resolveAssignmentEmployeeName($action, $context);
        $originalShiftDesc = self::resolveAssignmentShiftDescription($action, $context);

        $newTimeStr = self::string($action, 'start_time') . ' - ' . self::string($action, 'end_time');

        $label = 'Update assignment of ' . $originalEmployeeName . ' on shift ' . $originalShiftDesc . ' to ' . $newTimeStr;

        $isReassigned = $id !== 0 && $assignment !== null && $id !== $assignment->getEmployeeProfileId();
        if ($isReassigned) {
            $label .= ' (reassigned to ' . self::resolveEmployeeName($action, $context) . ')';
        }

        return $label;
    }

    /**
     * Resolve employee name from employee_profile_id.
     *
     * @param array<string, mixed> $action
     * @param array{employees: array<int|string, EmployeeProfile>, stores: array<int|string, Store>, requirements: array<int|string, ShiftRequirement>, assignments: array<int|string, ShiftAssignment>, availabilities: array<int|string, EmployeeAvailability>} $context
     */
    private static function resolveEmployeeName(array $action, array $context): string
    {
        $id = self::intVal($action['employee_profile_id'] ?? 0);
        $employee = $context['employees'][$id] ?? null;

        return $employee !== null ? $employee->getName() : 'employee #' . $id;
    }

    /**
     * Resolve store name.
     *
     * @param array<string, mixed> $action
     * @param array{employees: array<int|string, EmployeeProfile>, stores: array<int|string, Store>, requirements: array<int|string, ShiftRequirement>, assignments: array<int|string, ShiftAssignment>, availabilities: array<int|string, EmployeeAvailability>} $context
     */
    private static function resolveStoreName(array $action, array $context): string
    {
        $id = self::intVal($action['store_id'] ?? 0);
        $store = $context['stores'][$id] ?? null;

        return $store !== null ? $store->getName() : 'store #' . $id;
    }

    /**
     * Resolve shift description (date, start/end time, role).
     *
     * @param array<string, mixed> $action
     * @param array{employees: array<int|string, EmployeeProfile>, stores: array<int|string, Store>, requirements: array<int|string, ShiftRequirement>, assignments: array<int|string, ShiftAssignment>, availabilities: array<int|string, EmployeeAvailability>} $context
     */
    private static function resolveShiftDescription(array $action, array $context): string
    {
        $id = self::intVal($action['shift_requirement_id'] ?? 0);
        $shift = $context['requirements'][$id] ?? null;
        if ($shift !== null) {
            $formattedDate = self::formatDate($shift->getDate());
            $formattedTime = \mb_substr($shift->getStartTime(), 0, 5) . ' - ' . \mb_substr($shift->getEndTime(), 0, 5);
            $roleStr = $shift->getRoleLabel() !== null ? ' (' . $shift->getRoleLabel() . ')' : '';

            return $formattedDate . ' ' . $formattedTime . $roleStr;
        }

        return '#' . $id;
    }

    /**
     * Resolve employee name via assignment.
     *
     * @param array<string, mixed> $action
     * @param array{employees: array<int|string, EmployeeProfile>, stores: array<int|string, Store>, requirements: array<int|string, ShiftRequirement>, assignments: array<int|string, ShiftAssignment>, availabilities: array<int|string, EmployeeAvailability>} $context
     */
    private static function resolveAssignmentEmployeeName(array $action, array $context): string
    {
        $id = self::intVal($action['shift_assignment_id'] ?? 0);
        $assignment = $context['assignments'][$id] ?? null;
        if ($assignment !== null && $assignment->getEmployeeProfile() !== null) {
            return $assignment->getEmployeeProfile()->getName();
        }

        return 'assignment #' . $id;
    }

    /**
     * Resolve shift description via assignment.
     *
     * @param array<string, mixed> $action
     * @param array{employees: array<int|string, EmployeeProfile>, stores: array<int|string, Store>, requirements: array<int|string, ShiftRequirement>, assignments: array<int|string, ShiftAssignment>, availabilities: array<int|string, EmployeeAvailability>} $context
     */
    private static function resolveAssignmentShiftDescription(array $action, array $context): string
    {
        $id = self::intVal($action['shift_assignment_id'] ?? 0);
        $assignment = $context['assignments'][$id] ?? null;
        if ($assignment !== null && $assignment->getShiftRequirement() !== null) {
            $shift = $assignment->getShiftRequirement();
            $formattedDate = self::formatDate($shift->getDate());
            $formattedTime = \mb_substr($shift->getStartTime(), 0, 5) . ' - ' . \mb_substr($shift->getEndTime(), 0, 5);

            return $formattedDate . ' ' . $formattedTime;
        }

        return '#' . $id;
    }

    /**
     * Resolve employee name via availability.
     *
     * @param array<string, mixed> $action
     * @param array{employees: array<int|string, EmployeeProfile>, stores: array<int|string, Store>, requirements: array<int|string, ShiftRequirement>, assignments: array<int|string, ShiftAssignment>, availabilities: array<int|string, EmployeeAvailability>} $context
     */
    private static function resolveAvailabilityEmployeeName(array $action, array $context): string
    {
        $id = self::intVal($action['availability_id'] ?? 0);
        $availability = $context['availabilities'][$id] ?? null;
        if ($availability !== null && $availability->getEmployeeProfile() !== null) {
            return $availability->getEmployeeProfile()->getName();
        }

        return 'availability #' . $id;
    }

    /**
     * Localize and format date safely.
     */
    private static function formatDate(string $dateStr): string
    {
        try {
            return Carbon::parse($dateStr)->format('j.n.Y');
        } catch (Throwable) {
            return $dateStr;
        }
    }

    /**
     * Helper to safely extract integer from mixed values without direct casting.
     */
    private static function intVal(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && \ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * String value from action.
     *
     * @param array<string, mixed> $action
     */
    private static function string(array $action, string $key): string
    {
        $value = $action[$key] ?? null;

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
