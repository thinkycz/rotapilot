<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreBusinessHoursEditController
{
    use ValidatesWebRequests;

    /**
     * Show the business hours editor.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $id = (int) $request->query('id', '0');
        $store = Store::query()->find($id);
        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!Authorization::canManageStore($user, $store)) {
            \abort(403);
        }

        $hours = $store->getBusinessHours();
        $byDay = [];
        foreach ($hours as $h) {
            $byDay[$h->getDayOfWeek()] = [
                'day_of_week' => $h->getDayOfWeek(),
                'opens_at' => $h->getOpensAt(),
                'closes_at' => $h->getClosesAt(),
                'is_closed' => $h->getIsClosed(),
            ];
        }
        for ($d = 1; $d <= 7; ++$d) {
            if (!isset($byDay[$d])) {
                $byDay[$d] = [
                    'day_of_week' => $d,
                    'opens_at' => null,
                    'closes_at' => null,
                    'is_closed' => false,
                ];
            }
        }
        \ksort($byDay);

        return Inertia::render('stores/BusinessHours', [
            'store' => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'timezone' => $store->getTimezone(),
            ],
            'hours' => \array_values($byDay),
        ]);
    }
}
