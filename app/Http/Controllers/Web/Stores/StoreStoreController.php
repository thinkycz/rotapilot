<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StoreValidity;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StoreStoreController
{
    use ValidatesWebRequests;

    /**
     * Create a new store and attach the creator as the manager.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();

        if (!$user->isAdmin() && !$user->isStoreManager()) {
            \abort(403);
        }

        $validity = StoreValidity::inject();
        $validated = $this->validateRequest($request, [
            'name' => $validity->name()->required()->toArray(),
            'address' => $validity->address()->nullable()->toArray(),
            'city' => $validity->city()->nullable()->toArray(),
            'timezone' => $validity->timezone()->required()->toArray(),
            'is_active' => $validity->isActive()->nullable()->toArray(),
        ]);

        $store = new Store();
        $store->forceFill([
            'name' => $validated->assertString('name'),
            'address' => $validated->assertNullableString('address'),
            'city' => $validated->assertNullableString('city'),
            'timezone' => $validated->assertString('timezone'),
            'is_active' => $validated->has('is_active') ? (bool) $validated->mixed('is_active') : true,
        ])->save();

        // Auto-attach the creator as a manager so they can immediately manage
        // the store (admins also become managers of stores they create).
        DB::table('store_manager_store')->updateOrInsert(
            ['user_id' => $user->getKey(), 'store_id' => $store->getKey()],
            ['updated_at' => \now(), 'created_at' => \now()],
        );

        $request->session()->flash('success', \__('Store created.'));

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
