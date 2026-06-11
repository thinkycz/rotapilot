<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\ScheduleConflict;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Agent that explains a conflict in plain English and suggests a fix.
 */
class ConflictExplainerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Constructor.
     */
    public function __construct(public readonly ScheduleConflict $conflict) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return \implode(' ', [
            'You are RotaPilot\'s conflict explanation assistant.',
            'Explain the following conflict in plain English and suggest a concrete fix.',
            "Conflict type: {$this->conflict->getType()->value}.",
            "Severity: {$this->conflict->getSeverity()->value}.",
            "Message: {$this->conflict->getMessage()}",
            $this->conflict->getSuggestedFix() !== null ? "Existing suggested fix: {$this->conflict->getSuggestedFix()}" : '',
        ]);
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'explanation' => $schema->string()->required(),
            'severity' => $schema->string()->enum(['info', 'warning', 'critical'])->required(),
            'suggested_fix' => $schema->string()->required(),
        ];
    }
}
