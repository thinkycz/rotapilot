<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ScheduleValidity;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ScheduleStoreController
{
    use ValidatesWebRequests;

    /**
     * Create a new schedule.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $actor = User::mustAuth();

        $validity = ScheduleValidity::inject();
        $validated = $this->validateRequest($request, [
            'name' => $validity->name()->required()->toArray(),
            'store_id' => 'required|integer|exists:stores,id',
            'month' => $validity->month()->required()->toArray(),
            'year' => $validity->year()->required()->toArray(),
        ]);

        $store = Store::query()->find($validated->assertInt('store_id'));
        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!Authorization::canManageStore($actor, $store)) {
            \abort(403);
        }

        $periodStart = CarbonImmutable::create(
            $validated->assertInt('year'),
            $validated->assertInt('month'),
            1,
        );
        if (!$periodStart instanceof CarbonImmutable) {
            \abort(422);
        }

        $schedule = new Schedule();
        $schedule->forceFill([
            'name' => $validated->assertString('name'),
            'store_id' => $store->getKey(),
            'period_start' => $periodStart->startOfMonth()->format('Y-m-d'),
            'period_end' => $periodStart->endOfMonth()->format('Y-m-d'),
            'status' => 'draft',
            'created_by' => $actor->getKey(),
        ])->save();

        $request->session()->flash('success', \__('Schedule created.'));

        return \redirect('/schedules/show?id=' . $schedule->getKey());
    }
}
