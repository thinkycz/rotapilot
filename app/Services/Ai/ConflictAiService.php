<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Agents\ConflictExplainerAgent;
use App\Models\ScheduleConflict;

/**
 * Orchestrates AI explanations of schedule conflicts. Uses
 * `ConflictExplainerAgent` when a provider is configured; otherwise
 * returns a structured local explanation derived from the conflict row.
 */
class ConflictAiService
{
    /**
     * Explain a conflict.
     *
     * @return array{explanation: string, severity: string, suggested_fix: string}
     */
    public function explain(ScheduleConflict $conflict): array
    {
        if (ScheduleAiService::hasProvider()) {
            $response = (new ConflictExplainerAgent($conflict))->prompt($conflict->getMessage());
            $payload = \json_decode((string) $response->text, true);
            if (\is_array($payload)) {
                return [
                    'explanation' => (string) ($payload['explanation'] ?? $conflict->getMessage()),
                    'severity' => (string) ($payload['severity'] ?? $conflict->getSeverity()->value),
                    'suggested_fix' => (string) ($payload['suggested_fix'] ?? ($conflict->getSuggestedFix() ?? 'No suggestion available.')),
                ];
            }
        }

        return [
            'explanation' => $conflict->getMessage() . ' ' .
                ($conflict->getSuggestedFix() !== null ? \__('Suggested fix: ') . $conflict->getSuggestedFix() : ''),
            'severity' => $conflict->getSeverity()->value,
            'suggested_fix' => $conflict->getSuggestedFix() ?? \__('No suggestion available.'),
        ];
    }
}
