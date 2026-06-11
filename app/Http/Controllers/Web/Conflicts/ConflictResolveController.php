<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Conflicts;

use App\Models\Schedule;
use App\Models\ScheduleConflict;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ConflictResolveController
{
    /**
     * Mark a conflict as resolved.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $conflict = ModelFinder::findOrAbort(ScheduleConflict::class, $id);

        $schedule = ModelFinder::findOrAbort(Schedule::class, $conflict->getScheduleId());

        if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
            \abort(403);
        }

        ScheduleConflict::query()->getQuery()->where('id', $id)->update([
            'resolved_at' => \now(),
            'updated_at' => \now(),
        ]);

        $request->session()->flash('success', \__('Conflict marked resolved.'));

        return \back();
    }
}
