<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AgentConversationDestroyController
{
    use ValidatesWebRequests;

    /**
     * Delete a conversation and its messages.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $user = User::mustAuth();

        if (!$user->isStoreManager()) {
            \abort(403, 'Unauthorized.');
        }

        $validated = $this->validateRequest($request, [
            'conversation_id' => 'required|string',
        ]);

        $conversationId = $validated->parseString('conversation_id');

        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        if ($conversation instanceof Conversation) {
            $conversation->messages()->delete();
            $conversation->delete();
        }

        $referer = (string) $request->headers->get('referer');
        $deletedOnActivePage = Str::contains($referer, 'conversation=' . $conversationId);

        $activeConversation = $deletedOnActivePage
            ? null
            : $this->conversationFromReferer($referer);

        Inertia::flash('success', \__('Conversation deleted.'));

        return \redirect($this->redirectPath($activeConversation));
    }

    /**
     * Extract the `conversation` query value from a referer URL, or null.
     */
    private function conversationFromReferer(string $referer): string|null
    {
        if ($referer === '') {
            return null;
        }

        $query = \parse_url($referer, \PHP_URL_QUERY);

        if (!\is_string($query) || $query === '') {
            return null;
        }

        \parse_str($query, $params);

        $value = $params['conversation'] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Build the canonical GET page for the agent after a write action.
     */
    private function redirectPath(string|null $conversationId): string
    {
        if ($conversationId === null) {
            return '/agent';
        }

        return '/agent?conversation=' . \urlencode($conversationId);
    }
}
