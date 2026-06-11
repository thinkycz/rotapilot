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

class StoreIndexController
{
    use ValidatesWebRequests;

    /**
     * Page size for the index view.
     */
    public const int TAKE = 25;

    /**
     * Show the stores index.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $stores = Authorization::managedStores($user);

        return Inertia::render('stores/Index', [
            'stores' => $stores->map(static fn(Store $s): array => [
                'id' => $s->getKey(),
                'name' => $s->getName(),
                'city' => $s->getCity(),
                'address' => $s->getAddress(),
                'is_active' => $s->getIsActive(),
                'today_hours' => self::todayHours($s),
            ])->values()->all(),
        ]);
    }

    /**
     * Get the today's business hours string for a store.
     */
    private static function todayHours(Store $store): string
    {
        $day = (int) \now()->format('N');
        $hour = $store->businessHours()->getQuery()->where('day_of_week', $day)->first();

        if ($hour === null) {
            return '—';
        }

        if ($hour->getIsClosed()) {
            return 'Closed';
        }

        $opens = $hour->getOpensAt();
        $closes = $hour->getClosesAt();

        if ($opens === null || $closes === null) {
            return '—';
        }

        return $opens . ' – ' . $closes;
    }
}
