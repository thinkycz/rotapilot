<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\SchedulingAgent;
use App\Models\AgentActionProposal;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;

class AgentProposalChatNotifier
{
    /**
     * Add a visible assistant message after a proposal is applied.
     */
    public function applied(AgentActionProposal $proposal, User $user): void
    {
        $this->createAssistantMessage($proposal, $user, $this->appliedMessage($proposal, $user));
    }

    /**
     * Add a visible assistant message after a proposal is rejected.
     */
    public function rejected(AgentActionProposal $proposal, User $user): void
    {
        $this->createAssistantMessage($proposal, $user, $this->rejectedMessage($proposal, $user));
    }

    /**
     * Persist a manager-visible assistant message without adding an internal user prompt.
     */
    private function createAssistantMessage(AgentActionProposal $proposal, User $user, string $content): void
    {
        ConversationMessage::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $proposal->getConversationId(),
            'user_id' => $user->getKey(),
            'agent' => SchedulingAgent::class,
            'role' => 'assistant',
            'content' => $content,
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);
    }

    /**
     * Build the localized applied-proposal confirmation.
     */
    private function appliedMessage(AgentActionProposal $proposal, User $user): string
    {
        $summary = $proposal->getSummary();
        $actionCount = \count($proposal->getActions());
        $conflictCount = $this->countConflicts($proposal->getResult());

        return match ($user->getLocale()) {
            'cs' => $conflictCount > 0
                ? "Hotovo, návrh „{$summary}“ jsem použil. Provedeno změn: {$actionCount}. Po použití jsem našel {$conflictCount} upozornění na konflikty."
                : "Hotovo, návrh „{$summary}“ jsem použil. Provedeno změn: {$actionCount}.",
            'sk' => $conflictCount > 0
                ? "Hotovo, návrh „{$summary}“ som použil. Počet zmien: {$actionCount}. Po použití som našiel {$conflictCount} upozornení na konflikty."
                : "Hotovo, návrh „{$summary}“ som použil. Počet zmien: {$actionCount}.",
            default => $conflictCount > 0
                ? "Done, I applied the proposal \"{$summary}\". Actions applied: {$actionCount}. I found {$conflictCount} conflict warning(s) afterward."
                : "Done, I applied the proposal \"{$summary}\". Actions applied: {$actionCount}.",
        };
    }

    /**
     * Build the localized rejected-proposal confirmation.
     */
    private function rejectedMessage(AgentActionProposal $proposal, User $user): string
    {
        $summary = $proposal->getSummary();

        return match ($user->getLocale()) {
            'cs' => "Dobře, návrh „{$summary}“ jsem odmítl. Můžete mi zadat nový návrh, až budete chtít.",
            'sk' => "Dobre, návrh „{$summary}“ som odmietol. Môžete mi zadať nový návrh, keď budete chcieť.",
            default => "Okay, I rejected the proposal \"{$summary}\". You can ask me for a new proposal whenever you are ready.",
        };
    }

    /**
     * Count total conflicts across all affected schedules.
     *
     * @param array<string, mixed>|null $result
     */
    private function countConflicts(array|null $result): int
    {
        if (!\is_array($result)) {
            return 0;
        }

        $rows = $result['conflicts'] ?? [];
        if (!\is_array($rows)) {
            return 0;
        }

        $total = 0;
        foreach ($rows as $row) {
            if (\is_array($row) && \is_array($row['conflicts'] ?? null)) {
                $total += \count($row['conflicts']);
            }
        }

        return $total;
    }
}
