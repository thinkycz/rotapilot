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

class StoreEditController
{
    use ValidatesWebRequests;

    /**
     * Show the edit form for a store.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();

        if (!$user->isAdmin() && !$user->isStoreManager()) {
            \abort(403);
        }

        $id = (int) $request->query('id', '0');
        $store = Store::query()->find($id);

        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!Authorization::canManageStore($user, $store)) {
            \abort(403);
        }

        return Inertia::render('stores/Edit', [
            'store' => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'city' => $store->getCity(),
                'timezone' => $store->getTimezone(),
                'is_active' => $store->getIsActive(),
            ],
            'timezones' => \timezone_identifiers_list(),
        ]);
    }
}
