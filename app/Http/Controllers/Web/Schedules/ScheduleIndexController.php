<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleIndexController
{
    /**
     * Page size for the index view.
     */
    public const int TAKE = 25;

    /**
     * Show the schedules list.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $managedStores = Authorization::managedStores($user);
        $storeIds = $managedStores->pluck('id')->all();

        $schedules = Schedule::query()
            ->whereIn('store_id', \count($storeIds) === 0 ? [0] : $storeIds)
            ->orderBy('period_start', 'desc')
            ->get();

        return Inertia::render('schedules/Index', [
            'schedules' => $schedules->map(static fn(Schedule $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
                'store_id' => $s->getStoreId(),
                'status' => $s->getStatus()->value,
                'period_start' => $s->getPeriodStart(),
                'period_end' => $s->getPeriodEnd(),
                'shift_count' => $s->shiftRequirements()->count(),
            ])->values()->all(),
            'stores' => $managedStores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
        ]);
    }
}
