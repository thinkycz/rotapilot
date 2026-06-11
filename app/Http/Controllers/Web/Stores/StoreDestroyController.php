<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StoreDestroyController
{
    /**
     * Delete a store.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $user = User::mustAuth();

        if (!$user->isAdmin()) {
            \abort(403);
        }

        $id = (int) $request->query('id', '0');
        $store = Store::query()->find($id);
        if (!$store instanceof Store) {
            \abort(404);
        }

        $store->delete();

        $request->session()->flash('success', \__('Store deleted.'));

        return \redirect('/stores/index');
    }
}
