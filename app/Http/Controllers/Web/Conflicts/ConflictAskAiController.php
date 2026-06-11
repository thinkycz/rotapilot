<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Conflicts;

use App\Models\Schedule;
use App\Models\ScheduleConflict;
use App\Models\User;
use App\Services\Ai\ConflictAiService;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ConflictAskAiController
{
    /**
     * Constructor.
     */
    public function __construct(private readonly ConflictAiService $ai) {}

    /**
     * Ask the AI to explain a conflict.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $conflict = ModelFinder::findOrAbort(ScheduleConflict::class, $id);

        $schedule = ModelFinder::findOrAbort(Schedule::class, $conflict->getScheduleId());
        if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
            \abort(403);
        }

        return \response()->json($this->ai->explain($conflict));
    }
}
