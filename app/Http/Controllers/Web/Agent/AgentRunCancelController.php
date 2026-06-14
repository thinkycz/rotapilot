<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentRunService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRunCancelController
{
    use ValidatesWebRequests;

    /**
     * Cancel a running background agent run.
     */
    public function __invoke(Request $request, AgentRunService $runs): JsonResponse
    {
        $user = User::mustAuth();

        if (!$user->isStoreManager()) {
            \abort(403, 'Unauthorized.');
        }

        $validated = $this->validateRequest($request, [
            'run_id' => 'required|string',
        ]);

        $run = $runs->cancel($user, $validated->parseString('run_id'));

        return \response()->json([
            'run_id' => $run->getId(),
            'status' => $run->getStatus(),
        ]);
    }
}
