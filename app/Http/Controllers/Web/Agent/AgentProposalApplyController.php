<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentProposalApplyService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\AgentActionProposal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class AgentProposalApplyController
{
    use ValidatesWebRequests;

    /**
     * Apply a pending proposal.
     */
    public function __invoke(Request $request, AgentProposalApplyService $service): RedirectResponse
    {
        $user = User::mustAuth();

        if (!$user->isStoreManager()) {
            \abort(403, 'Unauthorized.');
        }

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
            $service->apply($proposal, $user);
            $request->session()->flash('success', \__('Proposal applied.'));
        } catch (Throwable $throwable) {
            $request->session()->flash('error', $throwable->getMessage());
        }

        return \redirect()->back(fallback: '/agent?conversation=' . $proposal->getConversationId());
    }
}
