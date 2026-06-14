<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentProposalApplyService;
use App\Ai\AgentProposalChatNotifier;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\AgentActionProposal;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class AgentProposalRejectController
{
    use ValidatesWebRequests;

    /**
     * Reject a pending proposal.
     */
    public function __invoke(
        Request $request,
        AgentProposalApplyService $service,
        AgentProposalChatNotifier $notifier,
    ): SymfonyResponse {
        \set_time_limit(0);

        $user = User::mustAuth();

        Authorization::mustBeStoreManager($user);

        $validated = $this->validateRequest($request, [
            'proposal_id' => 'required|integer|exists:agent_action_proposals,id',
        ]);

        $proposal = AgentActionProposal::query()
            ->where('id', $validated->assertInt('proposal_id'))
            ->where('user_id', $user->getKey())
            ->first();

        if (!$proposal instanceof AgentActionProposal) {
            \abort(404);
        }

        try {
            $service->reject($proposal, $user);
            $notifier->rejected($proposal, $user);
        } catch (Throwable $throwable) {
            // Rejections generally do not fail, but caught just in case.
        }

        return \redirect($this->redirectPath($proposal->getConversationId()));
    }

    /**
     * Build the canonical GET page for the agent after rejecting a proposal.
     */
    private function redirectPath(string $conversationId): string
    {
        return '/agent?conversation=' . \urlencode($conversationId);
    }
}
