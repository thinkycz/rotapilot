<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ScheduleValidity;
use App\Models\Schedule;
use App\Support\ModelFinder;
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
        $id = (int) $request->query('id', '0');
        $schedule = ModelFinder::findOrAbort(Schedule::class, $id);

        $validity = ScheduleValidity::inject();
        $validated = $this->validateRequest($request, [
            'name' => $validity->name()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'year' => $validity->year()->required()->toArray(),
        ]);

        $periodStart = CarbonImmutable::create(
            $validated->assertInt('year'),
            $validated->assertInt('month'),
            1,
        );
        if (!$periodStart instanceof CarbonImmutable) {
            \abort(422);
        }

        $schedule->forceFill([
            'name' => $validated->assertString('name'),
            'period_start' => $periodStart->startOfMonth()->format('Y-m-d'),
            'period_end' => $periodStart->endOfMonth()->format('Y-m-d'),
        ])->save();

        $request->session()->flash('success', \__('Schedule updated.'));

        return \redirect('/schedules/show?id=' . $schedule->getKey());
    }
}
