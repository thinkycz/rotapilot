<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ScheduleValidity;
use App\Models\Schedule;
use App\Support\ModelFinder;
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
            'period_start' => $validity->periodStart()->required()->toArray(),
            'period_end' => $validity->periodEnd()->required()->toArray(),
        ]);

        $schedule->forceFill([
            'name' => $validated->assertString('name'),
            'period_start' => $validated->assertString('period_start'),
            'period_end' => $validated->assertString('period_end'),
        ])->save();

        $request->session()->flash('success', \__('Schedule updated.'));

        return \redirect('/schedules/show?id=' . $schedule->getKey());
    }
}
