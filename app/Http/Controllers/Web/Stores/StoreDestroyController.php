<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Models\Store;
use App\Models\User;
use App\Support\Authorization;
use App\Support\ModelFinder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StoreDestroyController
{
    /**
     * Delete a store.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $id = (int) $request->query('id', '0');
        $store = ModelFinder::findOrAbort(Store::class, $id);

        Authorization::mustDeleteStore(User::mustAuth(), $store);

        $store->delete();

        $request->session()->flash('success', \__('Store deleted.'));

        return \redirect('/stores/index');
    }
}
