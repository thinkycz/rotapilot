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

                $clarification = $this->clarificationPayload($decoded);
                if ($clarification === null) {
                    continue;
                }

                $meta = $message->getAttribute('meta') ?? [];
                if (!\is_array($meta)) {
                    $meta = [];
                }
                $meta['clarification'] = $clarification;

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
                if (\is_array($decoded)) {
                    $clarification = $this->clarificationPayload($decoded);
                    if ($clarification === null) {
                        return;
                    }

                    $meta = $message->getAttribute('meta') ?? [];
                    if (!\is_array($meta)) {
                        $meta = [];
                    }
                    $meta['clarification'] = $clarification;

                    $message->setAttribute('meta', $meta);
                    $message->setAttribute('content', $clarification['question']);
                    $message->save();
                }
            }
        }
    }

    /**
     * Normalize a clarification payload from tool results or legacy JSON content.
     *
     * @param array<mixed> $payload
     *
     * @return array{question: string, options: array<int, string>, recommended_option: string|null}|null
     */
    private function clarificationPayload(array $payload): array|null
    {
        $question = $payload['question'] ?? null;
        $rawOptions = $payload['options'] ?? null;

        if (!\is_string($question) || \trim($question) === '' || !\is_array($rawOptions)) {
            return null;
        }

        $options = [];
        foreach ($rawOptions as $option) {
            if (!\is_string($option) || \trim($option) === '' || $this->hasOptionPrefix($option)) {
                return null;
            }

            $normalized = \trim($option);
            if (!\in_array($normalized, $options, true)) {
                $options[] = $normalized;
            }
        }

        if (\count($options) < 2 || \count($options) > 5) {
            return null;
        }

        $recommended = $payload['recommended_option'] ?? null;
        if ($recommended === '') {
            $recommended = null;
        }

        if ($recommended !== null && (!\is_string($recommended) || !\in_array(\trim($recommended), $options, true))) {
            return null;
        }

        return [
            'question' => \trim($question),
            'options' => $options,
            'recommended_option' => \is_string($recommended) ? \trim($recommended) : null,
        ];
    }

    /**
     * Detect option labels that are rendered by the frontend.
     */
    private function hasOptionPrefix(string $value): bool
    {
        return \preg_match('/^[A-Z][:.)-]\\s*/i', \trim($value)) === 1;
    }
}
