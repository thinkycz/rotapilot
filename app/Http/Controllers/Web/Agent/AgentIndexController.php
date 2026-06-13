<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentPageLoader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentIndexController
{
    public const int TAKE = 20;

    /**
     * Show the AI Assistant page.
     */
    public function __invoke(Request $request, AgentPageLoader $loader): Response
    {
        return Inertia::render('agent/Index', $loader->load($request));
    }
}
