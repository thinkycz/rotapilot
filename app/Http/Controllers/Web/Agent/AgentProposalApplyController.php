<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentProposalApplyService;
use App\Ai\AgentProposalChatNotifier;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\AgentActionProposal;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class AgentProposalApplyController
{
    use ValidatesWebRequests;

    /**
     * Apply a pending proposal.
     */
    public function __invoke(
        Request $request,
        AgentProposalApplyService $service,
        AgentProposalChatNotifier $notifier,
    ): SymfonyResponse {
        \set_time_limit(0);
        $user = User::mustAuth();

        if (!$user->isStoreManager()) {
            \abort(403, 'Unauthorized.');
        }

        $validated = $this->validateRequest($request, [
            'proposal_id' => 'required|integer|exists:agent_action_proposals,id',
            'action_indexes' => 'nullable|array',
            'action_indexes.*' => 'integer',
        ]);

        $proposal = AgentActionProposal::query()
            ->where('id', $validated->assertInt('proposal_id'))
            ->where('user_id', $user->getKey())
            ->first();

        if (!$proposal instanceof AgentActionProposal) {
            \abort(404);
        }

        try {
            $actionIndexes = null;
            if ($request->has('action_indexes')) {
                $rawIndexes = $validated->assertArray('action_indexes');
                $actionIndexes = [];
                foreach ($rawIndexes as $val) {
                    if (\is_int($val)) {
                        $actionIndexes[] = $val;
                    } elseif (\is_string($val) && \ctype_digit($val)) {
                        $actionIndexes[] = (int) $val;
                    }
                }
            }
            $service->apply($proposal, $user, $actionIndexes);
            $notifier->applied($proposal, $user);
        } catch (Throwable $throwable) {
            // Error is caught and saved in the proposal database record via the service.
        }

        return \redirect($this->redirectPath($proposal->getConversationId()));
    }

    /**
     * Build the canonical GET page for the agent after applying a proposal.
     */
    private function redirectPath(string $conversationId): string
    {
        return '/agent?conversation=' . \urlencode($conversationId);
    }
}
