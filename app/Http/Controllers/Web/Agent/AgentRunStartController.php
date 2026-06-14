<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentRunAlreadyActiveException;
use App\Ai\AgentRunService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Typer;

class AgentRunStartController
{
    use ValidatesWebRequests;

    /**
     * Start a durable background agent run.
     */
    public function __invoke(Request $request, AgentRunService $runs): JsonResponse
    {
        $user = User::mustAuth();

        if (!$user->isStoreManager()) {
            \abort(403, 'Unauthorized.');
        }

        $validated = $this->validateRequest($request, [
            'prompt' => 'required|string',
            'conversation_id' => 'nullable|string',
        ]);

        try {
            $result = $runs->start(
                $user,
                $validated->parseString('prompt'),
                $validated->parseNullableString('conversation_id'),
            );
        } catch (AgentRunAlreadyActiveException $exception) {
            return \response()->json([
                'run_id' => Typer::assertString($exception->run->getId()),
                'conversation_id' => $exception->run->getConversationId(),
                'status' => $exception->run->getStatus(),
            ], 409);
        }

        return \response()->json([
            'run_id' => Typer::assertString($result['run']->getId()),
            'conversation_id' => Typer::assertString($result['conversation']->getKey()),
            'status' => $result['run']->getStatus(),
        ]);
    }
}
