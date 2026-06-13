<?php

declare(strict_types=1);

namespace App\Ai;

class AgentConversationContext
{
    /**
     * Current conversation id.
     */
    private string|null $conversationId = null;

    /**
     * Set the current conversation id.
     */
    public function setConversationId(string $conversationId): void
    {
        $this->conversationId = $conversationId;
    }

    /**
     * Get the current conversation id.
     */
    public function conversationId(): string|null
    {
        return $this->conversationId;
    }
}
