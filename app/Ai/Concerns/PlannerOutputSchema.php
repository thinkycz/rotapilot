<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Single source of truth for the schedule planner structured output
 * schema. Both `SchedulePlannerAgent` (real) and
 * `FakeSchedulePlannerAgent` (test) consume this so the schema
 * cannot drift.
 */
final class PlannerOutputSchema
{
    /**
     * Build the JSON schema definition for the planner output.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public static function build(JsonSchema $schema): array
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

    /**
     * Decode the response into a typed array.
     *
     * @return array{intent: string, understanding: string, warnings: array<int, string>, shift_requirements: array<int, array<string, mixed>>}
     */
    public static function decode(AgentResponse $response): array
    {
        $payload = \json_decode($response->text, true);
        if (!\is_array($payload)) {
            return [
                'intent' => 'noop',
                'understanding' => '',
                'warnings' => [],
                'shift_requirements' => [],
            ];
        }

        $warnings = [];
        foreach ((array) ($payload['warnings'] ?? []) as $warning) {
            if (\is_string($warning)) {
                $warnings[] = $warning;
            }
        }

        $requirements = [];
        foreach ((array) ($payload['shift_requirements'] ?? []) as $requirement) {
            if (\is_array($requirement)) {
                /** @var array<string, mixed> $typed */
                $typed = [];
                foreach ($requirement as $key => $value) {
                    $typed[\is_string($key) ? $key : (string) $key] = $value;
                }
                $requirements[] = $typed;
            }
        }

        return [
            'intent' => \is_string($payload['intent'] ?? null) ? $payload['intent'] : 'noop',
            'understanding' => \is_string($payload['understanding'] ?? null) ? $payload['understanding'] : '',
            'warnings' => $warnings,
            'shift_requirements' => $requirements,
        ];
    }
}
