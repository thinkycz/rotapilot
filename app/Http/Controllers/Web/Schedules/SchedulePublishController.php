<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Models\Schedule;
use App\Models\User;
use App\Services\Scheduling\ConflictDetectionService;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SchedulePublishController
{
    /**
     * Constructor.
     */
    public function __construct(private readonly ConflictDetectionService $conflicts) {}

    /**
     * Publish a schedule (if no critical conflicts).
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

        // Recompute conflicts before publishing.
        $this->conflicts->recompute($schedule);

        $critical = $schedule->conflicts()->get()->first(static fn($c): bool => $c->getSeverity()->value === 'critical');
        if ($critical !== null) {
            $request->session()->flash('error', \__('Cannot publish with critical conflicts.'));

            return \back();
        }

        $schedule->forceFill([
            'status' => 'published',
            'published_at' => \now(),
        ])->save();

        $request->session()->flash('success', \__('Schedule published.'));

        return \back();
    }
}
