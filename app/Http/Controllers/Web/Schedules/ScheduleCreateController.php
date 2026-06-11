<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedules;

use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleCreateController
{
    /**
     * Show the create form.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $stores = Authorization::managedStores($user);
        $defaultStore = $stores->first();

        return Inertia::render('schedules/Edit', [
            'schedule' => null,
            'stores' => $stores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
            ])->values()->all(),
            'default_store_id' => $defaultStore?->getKey() ?? 0,
        ]);
    }
}
