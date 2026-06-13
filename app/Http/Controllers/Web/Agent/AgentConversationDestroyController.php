<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;

class AgentConversationDestroyController
{
    use ValidatesWebRequests;

    /**
     * Delete a conversation and its messages.
     */
    public function __invoke(Request $request): RedirectResponse
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

        if ($conversation !== null) {
            $conversation->messages()->delete();
            $conversation->delete();
        }

        $previousUrl = $request->headers->get('referer');

        if (\is_string($previousUrl) && Str::contains($previousUrl, 'conversation=' . $conversationId)) {
            return \redirect('/agent');
        }

        return \redirect()->back(fallback: '/agent');
    }
}
