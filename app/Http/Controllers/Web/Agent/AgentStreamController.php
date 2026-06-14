<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentConversationContext;
use App\Ai\AgentProposalLinker;
use App\Ai\Agents\SchedulingAgent;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use App\Support\Authorization;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnexpectedValueException;

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
    ): StreamedResponse {
        // SSE streams run for as long as the LLM + tool-call round-trips take.
        // PHP-FPM's default 30 s limit kills the stream before RememberConversation
        // can persist messages, leaving zombie conversations with no history.
        \set_time_limit(0);

        $user = User::mustAuth();

        Authorization::mustBeStoreManager($user);

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

        $provisionalMessagePersisted = false;

        $stream = $agent->stream($prompt)
            ->then(function () use ($conversationId, $prompt): void {
                $this->deleteProvisionalUserMessages($conversationId, $prompt);
            })
            ->then(static function () use ($conversationId, $linker): void {
                $linker->linkProposalsToLatestAssistantMessage($conversationId);
                $linker->linkQuestionsToLatestAssistantMessage($conversationId);
            });

        return \response()->stream(function () use ($stream, $conversationId, $prompt, $user, &$provisionalMessagePersisted): Generator {
            foreach ($stream as $event) {
                if (!$provisionalMessagePersisted) {
                    $this->persistProvisionalUserMessage($conversationId, $prompt, $user);
                    $provisionalMessagePersisted = true;
                }

                if (!$event instanceof Stringable) {
                    throw new UnexpectedValueException('Streaming agent events must be stringable.');
                }

                yield 'data: ' . ((string) $event) . "\n\n";
            }

            yield "data: [DONE]\n\n";
        }, headers: ['Content-Type' => 'text/event-stream']);
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

    /**
     * Persist the manager message once the stream has started so abandoning the
     * page does not leave an empty conversation behind.
     */
    private function persistProvisionalUserMessage(string $conversationId, string $prompt, User $user): string
    {
        $messageId = (string) Str::uuid();

        ConversationMessage::query()->create([
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'user_id' => $user->getKey(),
            'agent' => SchedulingAgent::class,
            'role' => 'user',
            'content' => $prompt,
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => ['provisional' => true],
        ]);

        Conversation::query()
            ->where('id', $conversationId)
            ->update(['updated_at' => \now()]);

        return $messageId;
    }

    /**
     * After a complete stream, RememberConversation has already saved the
     * canonical user message. The provisional row is only for interrupted streams.
     */
    private function deleteProvisionalUserMessages(string $conversationId, string $prompt): void
    {
        ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('content', $prompt)
            ->where('meta->provisional', true)
            ->delete();
    }
}
