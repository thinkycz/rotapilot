<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentConversationContext;
use App\Ai\AgentProposalLinker;
use App\Ai\Agents\SchedulingAgent;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\StreamableAgentResponse;

class AgentStreamController
{
    use ValidatesWebRequests;

    /**
     * Handle the streaming AI chat.
     */
    public function __invoke(
        Request $request,
        AgentConversationContext $context,
        AgentProposalLinker $linker,
    ): StreamableAgentResponse {
        // SSE streams run for as long as the LLM + tool-call round-trips take.
        // PHP-FPM's default 30 s limit kills the stream before RememberConversation
        // can persist messages, leaving zombie conversations with no history.
        \set_time_limit(0);

        $user = User::mustAuth();

        if (!$user->isStoreManager()) {
            \abort(403, 'Unauthorized.');
        }

        $validated = $this->validateRequest($request, [
            'prompt' => 'required|string',
            'conversation_id' => 'nullable|string',
        ]);

        $prompt = $validated->parseString('prompt');
        $conversationId = $validated->parseNullableString('conversation_id');

        $agent = SchedulingAgent::make();

        if ($conversationId !== null) {
            $conversation = Conversation::query()
                ->where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();

            if ($conversation !== null) {
                $agent->continue($conversationId, as: $user);
                $context->setConversationId($conversationId);
            } else {
                $conversationId = $this->createConversation($prompt, $user);
                $agent->continue($conversationId, as: $user);
                $context->setConversationId($conversationId);
            }
        } else {
            $conversationId = $this->createConversation($prompt, $user);
            $agent->continue($conversationId, as: $user);
            $context->setConversationId($conversationId);
        }

        return $agent->stream($prompt)->then(static function () use ($conversationId, $linker): void {
            $linker->linkProposalsToLatestAssistantMessage($conversationId);
        });
    }

    /**
     * Create a conversation before streaming so tools can attach proposals to it.
     */
    private function createConversation(string $prompt, User $user): string
    {
        $conversationId = (string) Str::uuid();

        Conversation::query()->create([
            'id' => $conversationId,
            'user_id' => $user->getKey(),
            'title' => Str::limit($prompt, 100, preserveWords: true),
        ]);

        return $conversationId;
    }
}
