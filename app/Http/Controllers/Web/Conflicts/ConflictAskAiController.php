<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Conflicts;

use App\Models\Schedule;
use App\Models\ScheduleConflict;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ConflictAskAiController
{
    /**
     * Ask the AI to explain a conflict.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $row = ScheduleConflict::query()->getQuery()->getQuery()->where('id', $id)->first();
        if ($row === null) {
            \abort(404);
        }
        $conflict = Db::hydrateOne($row, ScheduleConflict::class);
        if ($conflict === null) {
            \abort(404);
        }

        $scheduleRow = Schedule::query()->getQuery()->getQuery()->where('id', $conflict->getScheduleId())->first();
        if ($scheduleRow === null) {
            \abort(404);
        }
        $schedule = Db::hydrateOne($scheduleRow, Schedule::class);
        if ($schedule === null) {
            \abort(404);
        }
        if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
            \abort(403);
        }

        // For MVP we just return a structured local explanation.
        return \response()->json([
            'explanation' => $conflict->getMessage() . ' ' .
                ($conflict->getSuggestedFix() !== null ? 'Suggested fix: ' . $conflict->getSuggestedFix() : ''),
            'severity' => $conflict->getSeverity()->value,
            'suggested_fix' => $conflict->getSuggestedFix() ?? 'No suggestion available.',
        ]);
    }
}
