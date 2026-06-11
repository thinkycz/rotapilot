<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleIndexController
{
    /**
     * Show the schedules list.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $managedStores = Authorization::managedStores($user);
        $storeIds = $managedStores->pluck('id')->all();

        $rows = Schedule::query()
            ->getQuery()
            ->whereIn('store_id', $storeIds ?: [0])
            ->orderBy('period_start', 'desc')
            ->get();

        $schedules = Db::hydrate($rows, Schedule::class);

        return Inertia::render('schedules/Index', [
            'schedules' => $schedules->map(static fn(Schedule $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
                'store_id' => $s->getStoreId(),
                'status' => $s->getStatus()->value,
                'period_start' => $s->getPeriodStart(),
                'period_end' => $s->getPeriodEnd(),
                'shift_count' => $s->shiftRequirements()->getQuery()->where('schedule_id', $s->getKey())->count(),
            ])->values()->all(),
            'stores' => $managedStores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
        ]);
    }
}
