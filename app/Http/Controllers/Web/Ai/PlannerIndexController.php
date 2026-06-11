<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Ai;

use App\Models\Store;
use App\Models\User;
use App\Services\Ai\ScheduleAiService;
use App\Support\Authorization;
use App\Support\Db;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlannerIndexController
{
    /**
     * Show the AI planner.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $storeId = (int) $request->query('store_id', '0');
        $periodStart = (string) $request->query('period_start', '');
        $periodEnd = (string) $request->query('period_end', '');
        $name = (string) $request->query('name', 'AI Schedule');
        $stores = Authorization::managedStores($user);

        $context = [
            'store' => null,
            'store_id' => $storeId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'name' => $name,
        ];

        if ($storeId > 0) {
            $row = Store::query()->getQuery()->getQuery()->where('id', $storeId)->first();
            $store = $row !== null ? Db::hydrateOne($row, Store::class) : null;
            if ($store instanceof Store) {
                $context['store'] = ['id' => $store->getKey(), 'name' => $store->getName()];
            }
        }

        $preview = $request->session()->get('ai_preview');

        return Inertia::render('ai/Planner', [
            'context' => $context,
            'stores' => $stores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
            'has_ai' => ScheduleAiService::hasProvider(),
            'preview' => $preview,
        ]);
    }
}
