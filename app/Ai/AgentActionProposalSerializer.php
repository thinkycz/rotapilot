<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AgentActionProposal;
use Illuminate\Support\Carbon;

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
        $summaries = [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $summaries[] = [
                'type' => \is_string($type) ? $type : 'unknown',
                'label' => self::label($action),
                'payload' => $action,
            ];
        }

        return $summaries;
    }

    /**
     * Human-readable action label.
     *
     * @param array<string, mixed> $action
     */
    private static function label(array $action): string
    {
        $type = \is_string($action['type'] ?? null) ? $action['type'] : 'unknown';

        return match ($type) {
            'store.create' => 'Create store "' . self::string($action, 'name') . '"',
            'store.update' => 'Update store #' . self::string($action, 'store_id'),
            'availability.create' => 'Create availability for employee #' . self::string($action, 'employee_profile_id') . ' on ' . self::string($action, 'date'),
            'availability.update' => 'Update availability #' . self::string($action, 'availability_id'),
            'availability.delete' => 'Delete availability #' . self::string($action, 'availability_id'),
            'shift.create' => 'Create shift on ' . self::string($action, 'date'),
            'shift.update' => 'Update shift #' . self::string($action, 'shift_requirement_id'),
            'shift.delete' => 'Delete shift #' . self::string($action, 'shift_requirement_id'),
            'shift.assign' => 'Assign employee #' . self::string($action, 'employee_profile_id') . ' to shift #' . self::string($action, 'shift_requirement_id'),
            'shift.unassign' => 'Remove assignment #' . self::string($action, 'shift_assignment_id'),
            'shift.autofill' => 'Auto-fill shift #' . self::string($action, 'shift_requirement_id'),
            default => $type,
        };
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
