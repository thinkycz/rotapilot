<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Models\Schedule;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ScheduleArchiveController
{
    /**
     * Archive a schedule.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $row = Schedule::query()->getQuery()->getQuery()->where('id', $id)->first();
        if ($row === null) {
            \abort(404);
        }
        $schedule = Db::hydrateOne($row, Schedule::class);
        if ($schedule === null) {
            \abort(404);
        }

        if (!Authorization::canManageSchedule(User::mustAuth(), $schedule)) {
            \abort(403);
        }

        $schedule->forceFill(['status' => 'archived'])->save();
        $request->session()->flash('success', \__('Schedule archived.'));

        return \back();
    }
}
