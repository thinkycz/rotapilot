<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AskClarifyingQuestionsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Ask the manager clarifying questions when their request/prompt is too vague or lacks required details (like schedule, store, employee, or times). Provide options to choose from, recommending the best one.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()
                ->description('The clarifying question to ask the manager.')
                ->required(),
            'options' => $schema->array()
                ->items($schema->string())
                ->description('List of multiple-choice options for the manager. Do NOT prefix the options with letters like "A:", "B.", or "A)". Only provide the raw description, as the letter badges are added automatically by the UI.')
                ->required(),
            'recommended_option' => $schema->string()
                ->description('One of the options from the list that is recommended by the AI.')
                ->required(),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $question = $request['question'] ?? '';
        $options = $request['options'] ?? [];
        $recommended = $request['recommended_option'] ?? null;

        $encoded = \json_encode([
            'question' => $question,
            'options' => $options,
            'recommended_option' => $recommended,
        ]);

        return \is_string($encoded) ? $encoded : '[]';
    }
}
