<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentActionProposalSerializer;
use App\Models\AgentActionProposal;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

class AgentIndexController
{
    public const int TAKE = 20;

    /**
     * Show the AI Assistant page.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();

        if (!$user->isStoreManager()) {
            \abort(403, 'Unauthorized.');
        }

        $conversationId = $request->input('conversation');
        $messages = [];
        $proposals = [];

        if (\is_string($conversationId)) {
            $conversation = Conversation::query()
                ->where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();

            if ($conversation !== null) {
                $messages = $conversation->messages()
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(static function (ConversationMessage $msg): array {
                        $id = Typer::assertString($msg->getAttribute('id'));
                        $role = Typer::assertString($msg->getAttribute('role'));
                        $content = Typer::assertString($msg->getAttribute('content'));
                        $createdAt = Typer::assertNullableCarbon($msg->getAttribute('created_at'));

                        return [
                            'id' => $id,
                            'role' => $role,
                            'content' => $content,
                            'created_at' => $createdAt !== null ? $createdAt->toIso8601String() : null,
                        ];
                    })->all();

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
        } else {
            $conversationId = null;
        }

        return Inertia::render('agent/Index', [
            'conversationId' => $conversationId,
            'messages' => $messages,
            'proposals' => $proposals,
        ]);
    }
}
