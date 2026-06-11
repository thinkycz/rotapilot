<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agent that turns a manager's natural-language description of a schedule
 * into structured shift requirements for a given store and period.
 */
class SchedulePlannerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Constructor.
     *
     * @param EloquentCollection<int, \App\Models\EmployeeProfile> $employees
     * @param array<int, string> $weekdayNotes
     */
    public function __construct(
        public readonly string $storeName,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd,
        public readonly EloquentCollection $employees,
        public readonly array $weekdayNotes = [],
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string|Stringable
    {
        $empList = $this->employees
            ->map(static fn($e): string => $e->getName() . ' (max ' . (($e->getMaxHoursPerWeek() !== null) ? $e->getMaxHoursPerWeek() . 'h' : 'unlimited') . ')')
            ->all();
        $employeeList = \implode('; ', $empList);

        $lines = [
            'You are RotaPilot\'s schedule planner.',
            "Store: {$this->storeName}.",
            "Period: {$this->periodStart->format('Y-m-d')} to {$this->periodEnd->format('Y-m-d')}.",
            "Employees assigned to this store: {$employeeList}.",
            'Convert the manager\'s natural-language description into structured shift requirements.',
            'Each requirement has a date in YYYY-MM-DD, start_time in HH:MM, end_time in HH:MM, required_employee_count (integer >= 1), optional role_label, optional note, and source=manual.',
            'For "weekdays 10:00-18:00" emit one requirement per weekday in the period with the same time and count.',
            'For "Saturday and Sunday 2 people 11:00-20:00" emit one requirement per weekend day in the period.',
            'Ignore employees not in the list above.',
        ];

        return \implode(' ', $lines);
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string()->enum(['create_or_update_schedule', 'noop'])->required(),
            'understanding' => $schema->string()->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
            'shift_requirements' => $schema->array()->items(
                $schema->object(fn(JsonSchema $s): array => [
                    'date' => $s->string()->required(),
                    'start_time' => $s->string()->required(),
                    'end_time' => $s->string()->required(),
                    'required_employee_count' => $s->integer()->min(1)->required(),
                    'role_label' => $s->string()->nullable(),
                    'note' => $s->string()->nullable(),
                ]),
            )->required(),
        ];
    }
}
