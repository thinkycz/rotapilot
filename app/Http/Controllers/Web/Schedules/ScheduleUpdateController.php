<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ScheduleValidity;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
use App\Support\ScheduleTitle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ScheduleUpdateController
{
    use ValidatesWebRequests;

    /**
     * Update a schedule.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $actor = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $schedule = ModelFinder::findOrAbort(Schedule::class, $id);

        Authorization::mustManageSchedule($actor, $schedule);

        $validity = ScheduleValidity::inject();
        $validated = $this->validateRequest($request, [
            'month' => $validity->month()->required()->toArray(),
            'year' => $validity->year()->required()->toArray(),
        ]);

        $month = $validated->assertInt('month');
        $year = $validated->assertInt('year');

        $periodStart = CarbonImmutable::create($year, $month, 1);
        if (!$periodStart instanceof CarbonImmutable) {
            \abort(422);
        }

        $schedule->loadMissing('store');
        $store = $schedule->getStore();

        $schedule->forceFill([
            'name' => ScheduleTitle::generate($store, $periodStart),
            'period_start' => $periodStart->startOfMonth()->format('Y-m-d'),
            'period_end' => $periodStart->endOfMonth()->format('Y-m-d'),
        ])->save();

        $request->session()->flash('success', \__('Schedule updated.'));

        return \redirect('/schedules/show?id=' . $schedule->getKey());
    }
}
