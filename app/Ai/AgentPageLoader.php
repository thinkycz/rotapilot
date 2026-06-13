<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AgentActionProposal;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

class AgentPageLoader
{
    /**
     * Load the conversation page data for the Inertia `agent/Index` component.
     *
     * Centralizes the conversation / messages / proposals shaping so the
     * index page and the proposal apply / reject / destroy endpoints stay in
     * lock-step and so those POST endpoints can re-render the same component
     * (Inertia v3 does not follow bare 302 redirects — see
     * `docs/lessons.md`).
     *
     * @return array{conversationId: string|null, messages: array<int, array{id: string, role: string, content: string, created_at: string|null, meta: array<string, mixed>|null, tool_calls: array<mixed>|null, tool_results: array<mixed>|null}>, proposals: array<int, array<string, mixed>>}
     */
    public function load(Request $request, string|null $conversationId = null): array
    {
        $user = User::mustAuth();

        if (!$user->isStoreManager()) {
            \abort(403, 'Unauthorized.');
        }

        $conversationId ??= $request->input('conversation');

        if (!\is_string($conversationId) || $conversationId === '') {
            $conversationId = null;
        }

        $messages = [];
        $proposals = [];

        if ($conversationId !== null) {
            $conversation = Conversation::query()
                ->where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();

            if ($conversation instanceof Conversation) {
                $messages = $this->visibleMessages($conversation->messages()
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->all());

                $proposals = AgentActionProposal::query()
                    ->where('conversation_id', $conversationId)
                    ->where('user_id', $user->getKey())
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(static fn(AgentActionProposal $proposal): array => AgentActionProposalSerializer::serialize($proposal))
                    ->all();
            } else {
                $conversationId = null;
            }
        }

        return [
            'conversationId' => $conversationId,
            'messages' => $messages,
            'proposals' => $proposals,
        ];
    }

    /**
     * Return only manager-visible conversation messages.
     *
     * @param array<int, ConversationMessage> $messages
     *
     * @return array<int, array{id: string, role: string, content: string, created_at: string|null, meta: array<string, mixed>|null, tool_calls: array<mixed>|null, tool_results: array<mixed>|null}>
     */
    private function visibleMessages(array $messages): array
    {
        $visible = [];
        $skipNextAssistant = false;

        foreach ($messages as $msg) {
            $role = Typer::assertString($msg->getAttribute('role'));
            $content = Typer::assertNullableString($msg->getAttribute('content')) ?? '';

            if ($this->isInternalConfirmationPrompt($role, $content)) {
                $skipNextAssistant = true;

                continue;
            }

            if ($skipNextAssistant && $role === 'assistant') {
                $skipNextAssistant = false;

                continue;
            }

            $skipNextAssistant = false;
            $createdAt = Typer::assertNullableCarbon($msg->getAttribute('created_at'));

            $meta = $msg->getAttribute('meta');
            if (\is_array($meta)) {
                $meta = Typer::assertStringKeyArray($meta);
            } else {
                $meta = null;
            }

            $toolCalls = $msg->getAttribute('tool_calls');
            $toolResults = $msg->getAttribute('tool_results');

            $visible[] = [
                'id' => Typer::assertString($msg->getAttribute('id')),
                'role' => $role,
                'content' => $content,
                'created_at' => $createdAt !== null ? $createdAt->toIso8601String() : null,
                'meta' => $meta,
                'tool_calls' => \is_array($toolCalls) ? $toolCalls : null,
                'tool_results' => \is_array($toolResults) ? $toolResults : null,
            ];
        }

        return $visible;
    }

    /**
     * Determine whether a persisted prompt came from an internal proposal event.
     */
    private function isInternalConfirmationPrompt(string $role, string $content): bool
    {
        return $role === 'user' && \str_contains($content, 'internal confirmation event');
    }
}
