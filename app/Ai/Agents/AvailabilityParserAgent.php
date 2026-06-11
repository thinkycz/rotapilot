<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\AvailabilityTypeEnum;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agent that turns a manager's natural-language description of an availability
 * pattern into structured availability records.
 */
class AvailabilityParserAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Constructor.
     *
     * @param EloquentCollection<int, \App\Models\EmployeeAvailability> $existingAvailabilities
     */
    public function __construct(
        public readonly string $employeeName,
        public readonly Carbon $referenceMonth,
        public readonly EloquentCollection $existingAvailabilities,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string|Stringable
    {
        $lines = [
            'You are RotaPilot\'s availability parser.',
            "The employee is {$this->employeeName}. The reference month is {$this->referenceMonth->format('F Y')}.",
            'Convert the manager\'s natural-language description into structured availability records.',
            'Each record has a date in YYYY-MM-DD format, a type (available, unavailable, preferred), optional start_time and end_time in HH:MM, and an optional note.',
            'For "every Monday" or "Mondays", use the actual dates in the reference month that fall on that weekday.',
            'For "cannot work <date range>", emit one unavailable row per day in the range, with no times.',
            'If a phrase indicates a preference, mark type=preferred. If it indicates an availability, mark type=available. If it indicates "cannot" or "not available", mark type=unavailable.',
            'Do not invent employees. The employee name is fixed.',
        ];

        return \implode(' ', $lines);
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'understanding' => $schema->string()->required(),
            'availability' => $schema->array()->items(
                $schema->object(fn(JsonSchema $s): array => [
                    'date' => $s->string()->required(),
                    'type' => $s->string()->enum(AvailabilityTypeEnum::values())->required(),
                    'start_time' => $s->string()->nullable(),
                    'end_time' => $s->string()->nullable(),
                    'note' => $s->string()->nullable(),
                ]),
            )->required(),
        ];
    }
}
