<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Stores;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreCreateController
{
    use ValidatesWebRequests;

    /**
     * Show the create store form.
     */
    public function __invoke(Request $request): Response
    {
        Authorization::mustCreateStore(User::mustAuth());

        return Inertia::render('stores/Edit', [
            'store' => null,
            'timezones' => \timezone_identifiers_list(),
        ]);
    }
}
