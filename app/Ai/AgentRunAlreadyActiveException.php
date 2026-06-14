<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AgentRun;
use RuntimeException;

class AgentRunAlreadyActiveException extends RuntimeException
{
    /**
     * Create the exception.
     */
    public function __construct(public readonly AgentRun $run)
    {
        parent::__construct('This conversation already has a running agent response.');
    }
}
