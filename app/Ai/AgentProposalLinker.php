<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AgentActionProposal;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

class AgentProposalLinker
{
    private const string PROPOSE_TOOL_NAME = 'ProposeSchedulingChangesTool';

    /**
     * Link proposals created by the latest assistant message in a conversation
     * to that message. Runs after the assistant message is stored so the
     * `message_id` is set on the proposal and the FE can render the proposal
     * inline as a "tool result" attached to the assistant turn.
     */
    public function linkProposalsToLatestAssistantMessage(string $conversationId): void
    {
        $message = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$message instanceof ConversationMessage) {
            return;
        }

        $messageId = Typer::assertString($message->getAttribute('id'));
        $toolResults = $message->getAttribute('tool_results');

        if (!\is_array($toolResults)) {
            return;
        }

        foreach ($toolResults as $toolResult) {
            if (!\is_array($toolResult)) {
                continue;
            }

            $name = $toolResult['name'] ?? null;
            if ($name !== self::PROPOSE_TOOL_NAME) {
                continue;
            }

            $result = $toolResult['result'] ?? null;
            if (!\is_string($result)) {
                continue;
            }

            $decoded = \json_decode($result, true);
            if (!\is_array($decoded)) {
                continue;
            }

            $proposalId = $decoded['proposal_id'] ?? null;
            if (\is_int($proposalId)) {
                // Use as-is.
            } elseif (\is_string($proposalId) && \ctype_digit($proposalId)) {
                $proposalId = (int) $proposalId;
            } else {
                continue;
            }

            AgentActionProposal::query()
                ->where('id', $proposalId)
                ->whereNull('message_id')
                ->update(['message_id' => $messageId]);
        }
    }

    /**
     * Link clarifying questions created by the latest assistant message in a conversation.
     */
    public function linkQuestionsToLatestAssistantMessage(string $conversationId): void
    {
        $message = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$message instanceof ConversationMessage) {
            return;
        }

        $toolResults = $message->getAttribute('tool_results');
        $linked = false;

        if (\is_array($toolResults)) {
            foreach ($toolResults as $toolResult) {
                if (!\is_array($toolResult)) {
                    continue;
                }

                $name = $toolResult['name'] ?? null;
                if ($name !== 'AskClarifyingQuestionsTool') {
                    continue;
                }

                $result = $toolResult['result'] ?? null;
                if (!\is_string($result)) {
                    continue;
                }

                $decoded = \json_decode($result, true);
                if (!\is_array($decoded)) {
                    continue;
                }

                $meta = $message->getAttribute('meta') ?? [];
                if (!\is_array($meta)) {
                    $meta = [];
                }
                $meta['clarification'] = [
                    'question' => $decoded['question'] ?? '',
                    'options' => $decoded['options'] ?? [],
                    'recommended_option' => $decoded['recommended_option'] ?? null,
                ];

                $message->setAttribute('meta', $meta);
                $message->save();
                $linked = true;
            }
        }

        if (!$linked) {
            $content = \trim(Typer::assertNullableString($message->getAttribute('content')) ?? '');
            if ($content !== '') {
                $jsonStr = $content;
                if (\preg_match('/```json\\s*(.*?)\\s*```/s', $content, $matches) === 1) {
                    $jsonStr = $matches[1];
                } elseif (\preg_match('/```\\s*(.*?)\\s*```/s', $content, $matches) === 1) {
                    $jsonStr = $matches[1];
                }

                $decoded = \json_decode($jsonStr, true);
                if (\is_array($decoded) && isset($decoded['question'], $decoded['options'])) {
                    $meta = $message->getAttribute('meta') ?? [];
                    if (!\is_array($meta)) {
                        $meta = [];
                    }
                    $meta['clarification'] = [
                        'question' => $decoded['question'],
                        'options' => $decoded['options'],
                        'recommended_option' => $decoded['recommended_option'] ?? null,
                    ];

                    $message->setAttribute('meta', $meta);
                    $message->setAttribute('content', $decoded['question']);
                    $message->save();
                }
            }
        }
    }
}
