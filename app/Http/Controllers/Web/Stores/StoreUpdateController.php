<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StoreValidity;
use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StoreUpdateController
{
    use ValidatesWebRequests;

    /**
     * Update a store.
     */
    public function __invoke(Request $request): SymfonyResponse
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

        $validity = StoreValidity::inject();
        $validated = $this->validateRequest($request, [
            'name' => $validity->name()->required()->toArray(),
            'address' => $validity->address()->nullable()->toArray(),
            'city' => $validity->city()->nullable()->toArray(),
            'timezone' => $validity->timezone()->required()->toArray(),
            'is_active' => $validity->isActive()->nullable()->toArray(),
        ]);

        $store->forceFill([
            'name' => $validated->assertString('name'),
            'address' => $validated->assertNullableString('address'),
            'city' => $validated->assertNullableString('city'),
            'timezone' => $validated->assertString('timezone'),
            'is_active' => $validated->has('is_active') ? (bool) $validated->mixed('is_active') : false,
        ])->save();

        $request->session()->flash('success', \__('Store updated.'));

        return \redirect('/stores/show?id=' . $store->getKey());
    }
}
