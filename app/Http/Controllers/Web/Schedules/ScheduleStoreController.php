<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ScheduleValidity;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
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
            'period_start' => $validity->periodStart()->required()->toArray(),
            'period_end' => $validity->periodEnd()->required()->toArray(),
        ]);

        $store = Store::query()->find((int) $validated->mixed('store_id'));
        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!Authorization::canManageStore($actor, $store)) {
            \abort(403);
        }

        $schedule = new Schedule();
        $schedule->forceFill([
            'name' => $validated->assertString('name'),
            'store_id' => $store->getKey(),
            'period_start' => $validated->assertString('period_start'),
            'period_end' => $validated->assertString('period_end'),
            'status' => 'draft',
            'created_by' => $actor->getKey(),
        ])->save();

        $request->session()->flash('success', \__('Schedule created.'));

        return \redirect('/schedules/show?id=' . $schedule->getKey());
    }
}
