<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleEditController
{
    use ValidatesWebRequests;

    /**
     * Show the edit form.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $row = Schedule::query()->getQuery()->getQuery()->where('id', $id)->first();
        if ($row === null) {
            \abort(404);
        }
        $schedule = Db::hydrateOne($row, Schedule::class);
        if ($schedule === null) {
            \abort(404);
        }

        $stores = Authorization::managedStores($user);

        return Inertia::render('schedules/Edit', [
            'schedule' => [
                'id' => $schedule->getKey(),
                'name' => $schedule->getName(),
                'store_id' => $schedule->getStoreId(),
                'period_start' => $schedule->getPeriodStart(),
                'period_end' => $schedule->getPeriodEnd(),
                'status' => $schedule->getStatus()->value,
            ],
            'stores' => $stores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
        ]);
    }
}
